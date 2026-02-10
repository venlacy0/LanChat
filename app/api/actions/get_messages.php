<?php
// 更新用户最后在线时间
updateLastSeen($_SESSION['user_id']);

// 分页参数
$limit = intval($_POST['limit'] ?? 50);
$before_id = intval($_POST['before_id'] ?? 0);

// 限制每页最大数量，防止恶意请求
$limit = max(1, min($limit, 10000));

try {
    $mysqli = get_db_connection();

    // 构建查询条件
    $where = "WHERE recalled = FALSE";
    $params = [];
    $types = "";

    if ($before_id > 0) {
        $where .= " AND id < ?";
        $params[] = $before_id;
        $types .= "i";
    }

    // 查询消息
    $query = "
        SELECT
            id,
            user_id,
            username,
            avatar,
            message,
            reply_to_id,
            UNIX_TIMESTAMP(timestamp) as timestamp,
            ip,
            recalled
        FROM public_messages
        $where
        ORDER BY id DESC
        LIMIT ?
    ";

    $params[] = $limit + 1; // 多查一条用于判断是否有更多
    $types .= "i";

    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        $mysqli->close();
        echo json_encode(['success' => false, 'message' => '查询失败']);
        exit;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    $hasMore = false;

    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $count++;
        if ($count > $limit) {
            $hasMore = true;
            break;
        }

        // 如果有 reply_to_id，查询被回复的消息
        $reply_to = null;
        if ($row['reply_to_id']) {
            $reply_stmt = $mysqli->prepare("
                SELECT username, avatar, message
                FROM public_messages
                WHERE id = ?
            ");
            $reply_stmt->bind_param("i", $row['reply_to_id']);
            $reply_stmt->execute();
            if ($reply_result = $reply_stmt->get_result()->fetch_assoc()) {
                $reply_to = [
                    'message' => $reply_result['message'],
                    'username' => $reply_result['username'],
                    'avatar' => $reply_result['avatar']
                ];
            }
            $reply_stmt->close();
        }

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

        if ($reply_to && !empty($reply_to['message'])) {
            // 检查回复消息是否为文件消息
            $isReplyFileMessage = false;
            if (is_string($reply_to['message'])) {
                $trimmed = trim($reply_to['message']);
                if (strpos($trimmed, '{') === 0) {
                    $decoded = json_decode($reply_to['message'], true);
                    if ($decoded && isset($decoded['type']) && $decoded['type'] === 'file') {
                        $isReplyFileMessage = true;
                    }
                }
            }

            if (!$isReplyFileMessage) {
                $reply_to['message'] = customParseAllowHtml($reply_to['message'], $parsedown);
            }
        }

        $messages[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'avatar' => $row['avatar'],
            'message' => $row['message'],
            'reply_to' => $reply_to,
            'timestamp' => $row['timestamp'],
            'ip' => $row['ip'],
            'recalled' => (bool)$row['recalled']
        ];
    }

    $stmt->close();
    $mysqli->close();

    // 标记当前用户已读公聊消息
    markPublicMessagesAsRead($_SESSION['user_id']);

    echo json_encode([
        'success' => true,
        'messages' => array_reverse($messages), // 返回时按时间升序
        'hasMore' => $hasMore,
        'count' => count($messages)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '获取消息失败: ' . $e->getMessage()]);
}
