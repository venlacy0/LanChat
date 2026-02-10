<?php
$messageId = intval($_POST['message_id'] ?? 0);
$isPrivate = filter_var($_POST['is_private'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

if (empty($messageId)) {
    echo json_encode(['success' => false, 'message' => '消息 ID 无效']);
    exit;
}

if ($isPrivate) {
    $mysqli = get_db_connection();
    $stmt = $mysqli->prepare("SELECT id FROM private_messages WHERE id = ? AND sender_id = ? AND recalled = FALSE");
    $stmt->bind_param("ii", $messageId, $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $updateStmt = $mysqli->prepare("UPDATE private_messages SET recalled = TRUE WHERE id = ?");
        $updateStmt->bind_param("i", $messageId);
        if ($updateStmt->execute()) {
            logAccess("Private message recalled by {$_SESSION['username']}, message_id: {$messageId}");
            echo json_encode(['success' => true, 'message' => '私聊消息已撤回']);
        } else {
            echo json_encode(['success' => false, 'message' => '撤回失败: ' . $mysqli->error]);
        }
        $updateStmt->close();
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => '消息未找到或无权撤回']);
    }
    $mysqli->close();
} else {
    // 公聊消息撤回
    $mysqli = get_db_connection();
    $stmt = $mysqli->prepare("SELECT id FROM public_messages WHERE id = ? AND user_id = ? AND recalled = FALSE");
    $stmt->bind_param("ii", $messageId, $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $updateStmt = $mysqli->prepare("UPDATE public_messages SET recalled = TRUE WHERE id = ?");
        $updateStmt->bind_param("i", $messageId);
        if ($updateStmt->execute()) {
            logAccess("Public message recalled by {$_SESSION['username']}, message_id: {$messageId}");
            echo json_encode(['success' => true, 'message' => '公共消息已撤回']);
        } else {
            echo json_encode(['success' => false, 'message' => '撤回失败: ' . $mysqli->error]);
        }
        $updateStmt->close();
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => '消息未找到或无权撤回']);
    }
    $mysqli->close();
}
