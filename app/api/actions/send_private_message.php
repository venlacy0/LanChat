<?php
// 更新用户最后在线时间
updateLastSeen($_SESSION['user_id']);

$receiver_id = intval($_POST['receiver_id'] ?? 0);
$message = trim($_POST['private_message'] ?? '');
$replyToId = !empty($_POST['reply_to']) ? intval($_POST['reply_to']) : null;

// 移除控制字符，但保留换行符（\n = 0x0A）和制表符（\t = 0x09）
// 删除：0x00-0x08, 0x0B-0x1F, 0x7F（除了 \t 和 \n）
$message = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $message);

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => '消息内容不能为空']);
    exit;
}
if ($receiver_id <= 0) {
    echo json_encode(['success' => false, 'message' => '请选择接收者']);
    exit;
}

if (!checkRateLimit($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '发送太频繁，请稍后再试']);
    exit;
}

$mysqli = get_db_connection();
if ($replyToId !== null) {
    $stmt = $mysqli->prepare("SELECT id FROM private_messages WHERE id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))");
    $stmt->bind_param("iiiii", $replyToId, $_SESSION['user_id'], $receiver_id, $receiver_id, $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $replyToId = null;
    }
    $stmt->close();
}

$stmt = $mysqli->prepare("INSERT INTO private_messages (sender_id, receiver_id, message, reply_to_id, timestamp, recalled) VALUES (?, ?, ?, ?, NOW(), FALSE)");
$stmt->bind_param("iisi", $_SESSION['user_id'], $receiver_id, $message, $replyToId);

if ($stmt->execute()) {
    $messageId = $mysqli->insert_id;
    $stmt->close();
    $stmt = $mysqli->prepare("
        SELECT pm.*, u1.username as sender_username, u1.avatar as sender_avatar, u2.username as receiver_username
        FROM private_messages pm
        JOIN users u1 ON pm.sender_id = u1.id
        JOIN users u2 ON pm.receiver_id = u2.id
        WHERE pm.id = ?
    ");
    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $newMessage = $result->fetch_assoc();
    $newMessage['message'] = customParseAllowHtml($newMessage['message'], $parsedown);
    $newMessage['timestamp'] = strtotime($newMessage['timestamp']);
    if ($newMessage['reply_to_id']) {
        $replyStmt = $mysqli->prepare("
            SELECT pm.message, u1.username as sender_username, u1.avatar as sender_avatar
            FROM private_messages pm
            JOIN users u1 ON pm.sender_id = u1.id
            WHERE pm.id = ?
        ");
        $replyStmt->bind_param("i", $newMessage['reply_to_id']);
        $replyStmt->execute();
        $replyResult = $replyStmt->get_result();
        if ($reply = $replyResult->fetch_assoc()) {
            $newMessage['reply_to'] = [
                'message' => customParseAllowHtml($reply['message'], $parsedown),
                'username' => $reply['sender_username'],
                'avatar' => $reply['sender_avatar']
            ];
        }
        $replyStmt->close();
    } else {
        $newMessage['reply_to'] = null;
    }

    echo json_encode(['success' => true, 'new_message' => $newMessage]);
} else {
    error_log("Private message insert failed: " . $mysqli->error);
    echo json_encode(['success' => false, 'message' => '发送失败: ' . $mysqli->error]);
}
$stmt->close();
$mysqli->close();
