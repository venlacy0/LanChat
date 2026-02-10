<?php
// 更新用户最后在线时间
updateLastSeen($_SESSION['user_id']);

$receiver_id = intval($_POST['receiver_id'] ?? 0);
if ($receiver_id <= 0) {
    echo json_encode(['success' => false, 'message' => '请选择接收者']);
    exit;
}

// 分页参数
$limit = intval($_POST['limit'] ?? 50); // 每页消息数（默认改为50）
$before_id = intval($_POST['before_id'] ?? 0); // 加载此 ID 之前的消息（用于向上滚动加载历史）

// 限制每页最大数量，防止恶意请求
$limit = max(1, min($limit, 10000));

$mysqli = get_db_connection();

// 构建查询条件
if ($before_id > 0) {
    // 加载历史消息（ID 小于 before_id 的消息）
    $stmt = $mysqli->prepare("
        SELECT pm.*, u1.username as sender_username, u1.avatar as sender_avatar, u2.username as receiver_username
        FROM private_messages pm
        JOIN users u1 ON pm.sender_id = u1.id
        JOIN users u2 ON pm.receiver_id = u2.id
        WHERE ((pm.sender_id = ? AND pm.receiver_id = ?) OR (pm.sender_id = ? AND receiver_id = ?))
        AND pm.recalled = FALSE
        AND pm.id < ?
        ORDER BY pm.timestamp DESC
        LIMIT ?
    ");
    $stmt->bind_param("iiiiii", $_SESSION['user_id'], $receiver_id, $receiver_id, $_SESSION['user_id'], $before_id, $limit);
} else {
    // 首次加载（最新的消息）
    $stmt = $mysqli->prepare("
        SELECT pm.*, u1.username as sender_username, u1.avatar as sender_avatar, u2.username as receiver_username
        FROM private_messages pm
        JOIN users u1 ON pm.sender_id = u1.id
        JOIN users u2 ON pm.receiver_id = u2.id
        WHERE ((pm.sender_id = ? AND pm.receiver_id = ?) OR (pm.sender_id = ? AND receiver_id = ?))
        AND pm.recalled = FALSE
        ORDER BY pm.timestamp DESC
        LIMIT ?
    ");
    $stmt->bind_param("iiiii", $_SESSION['user_id'], $receiver_id, $receiver_id, $_SESSION['user_id'], $limit);
}
$stmt->execute();
$result = $stmt->get_result();
$messages = [];
while ($row = $result->fetch_assoc()) {
    // 检查是否为文件消息，如果是则不进行Markdown解析
    $isFileMessage = false;
    if (is_string($row['message'])) {
        $trimmed = trim($row['message']);
        if (strpos($trimmed, '{') === 0) {
            $decoded = json_decode($row['message'], true);
            if ($decoded && isset($decoded['type']) && $decoded['type'] === 'file') {
                $isFileMessage = true;
            }
        }
    }

    if (!$isFileMessage) {
        $row['message'] = customParseAllowHtml($row['message'], $parsedown);
    }

    if ($row['reply_to_id']) {
        $replyStmt = $mysqli->prepare("
            SELECT pm.message, u1.username as sender_username, u1.avatar as sender_avatar
            FROM private_messages pm
            JOIN users u1 ON pm.sender_id = u1.id
            WHERE pm.id = ?
        ");
        $replyStmt->bind_param("i", $row['reply_to_id']);
        $replyStmt->execute();
        $replyResult = $replyStmt->get_result();
        if ($reply = $replyResult->fetch_assoc()) {
            // 检查回复消息是否为文件消息
            $isReplyFileMessage = false;
            if (is_string($reply['message'])) {
                $trimmed = trim($reply['message']);
                if (strpos($trimmed, '{') === 0) {
                    $decoded = json_decode($reply['message'], true);
                    if ($decoded && isset($decoded['type']) && $decoded['type'] === 'file') {
                        $isReplyFileMessage = true;
                    }
                }
            }

            if (!$isReplyFileMessage) {
                $reply['message'] = customParseAllowHtml($reply['message'], $parsedown);
            }

            $row['reply_to'] = [
                'message' => $reply['message'],
                'username' => $reply['sender_username'],
                'avatar' => $reply['sender_avatar']
            ];
        }
        $replyStmt->close();
    } else {
        $row['reply_to'] = null;
    }
    $row['timestamp'] = strtotime($row['timestamp']);
    $messages[] = $row;
}
$stmt->close();

// 检查是否还有更多历史消息
$hasMore = false;
if (count($messages) > 0) {
    $oldestId = min(array_column($messages, 'id'));
    $checkStmt = $mysqli->prepare("
        SELECT COUNT(*) as count
        FROM private_messages pm
        WHERE ((pm.sender_id = ? AND pm.receiver_id = ?) OR (pm.sender_id = ? AND pm.receiver_id = ?))
        AND pm.recalled = FALSE
        AND pm.id < ?
    ");
    $checkStmt->bind_param("iiiii", $_SESSION['user_id'], $receiver_id, $receiver_id, $_SESSION['user_id'], $oldestId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $row = $checkResult->fetch_assoc();
    $hasMore = $row['count'] > 0;
    $checkStmt->close();
}

$mysqli->close();

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'hasMore' => $hasMore,
    'count' => count($messages)
]);
