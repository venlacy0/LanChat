<?php
// 更新用户最后在线时间
updateLastSeen($_SESSION['user_id']);

$lastPublicTimestamp = intval($_POST['lastPublicTimestamp'] ?? 0);
$lastPrivateTimestamp = intval($_POST['lastPrivateTimestamp'] ?? 0);
$currentReceiverId = intval($_POST['currentReceiverId'] ?? 0);

// 新增：ID 游标（优先使用，避免同秒多消息丢失）
$lastPublicId = intval($_POST['lastPublicId'] ?? 0);
$lastPrivateId = intval($_POST['lastPrivateId'] ?? 0);

// 新增：撤回检测范围（只检查当前已加载窗口的消息）
$minPublicId = intval($_POST['minPublicId'] ?? 0);
$maxPublicId = intval($_POST['maxPublicId'] ?? 0);
$minPrivateId = intval($_POST['minPrivateId'] ?? 0);
$maxPrivateId = intval($_POST['maxPrivateId'] ?? 0);

try {
    $mysqli = get_db_connection();

    // 1) 查询新的公聊消息
    if ($lastPublicId > 0) {
        $stmt = $mysqli->prepare("
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
            WHERE id > ? AND recalled = FALSE
            ORDER BY id ASC
        ");
        if (!$stmt) {
            throw new Exception('公聊查询准备失败');
        }
        $stmt->bind_param("i", $lastPublicId);
    } else {
        // 兼容旧客户端：按 timestamp 拉取
        $stmt = $mysqli->prepare("
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
            WHERE timestamp > FROM_UNIXTIME(?) AND recalled = FALSE
            ORDER BY timestamp ASC
        ");
        if (!$stmt) {
            throw new Exception('公聊查询准备失败');
        }
        $stmt->bind_param("i", $lastPublicTimestamp);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $parsedPublicMessages = [];
    $publicReplyCache = [];
    while ($row = $result->fetch_assoc()) {
        // 如果有 reply_to_id，查询被回复的消息（做简单缓存避免 N+1 放大）
        $reply_to = null;
        if (!empty($row['reply_to_id'])) {
            $rid = (int)$row['reply_to_id'];
            if (isset($publicReplyCache[$rid])) {
                $reply_to = $publicReplyCache[$rid];
            } else {
                $reply_stmt = $mysqli->prepare("
                    SELECT username, avatar, message
                    FROM public_messages
                    WHERE id = ?
                ");
                if ($reply_stmt) {
                    $reply_stmt->bind_param("i", $rid);
                    $reply_stmt->execute();
                    if ($reply_result = $reply_stmt->get_result()->fetch_assoc()) {
                        $reply_to = [
                            'message' => $reply_result['message'],
                            'username' => $reply_result['username'],
                            'avatar' => $reply_result['avatar']
                        ];
                        $publicReplyCache[$rid] = $reply_to;
                    } else {
                        $publicReplyCache[$rid] = null;
                    }
                    $reply_stmt->close();
                }
            }
        }

        // 检查是否为文件消息，如果是则不进行 Markdown 解析
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

        if (!empty($reply_to) && isset($reply_to['message']) && is_string($reply_to['message'])) {
            $isReplyFileMessage = false;
            $trimmedReply = trim($reply_to['message']);
            if (strpos($trimmedReply, '{') === 0) {
                $decodedReply = json_decode($reply_to['message'], true);
                if ($decodedReply && isset($decodedReply['type']) && $decodedReply['type'] === 'file') {
                    $isReplyFileMessage = true;
                }
            }
            if (!$isReplyFileMessage) {
                $reply_to['message'] = customParseAllowHtml($reply_to['message'], $parsedown);
            }
        }

        $parsedPublicMessages[] = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'],
            'avatar' => $row['avatar'],
            'message' => $row['message'],
            'reply_to' => $reply_to,
            'timestamp' => (int)$row['timestamp'],
            'ip' => $row['ip'],
            'recalled' => (bool)$row['recalled']
        ];
    }
    $stmt->close();

    // 2) 查询新的私聊消息
    $parsedPrivateMessages = [];
    if ($currentReceiverId > 0 && $_SESSION['user_id'] > 0) {
        if ($lastPrivateId > 0) {
            $stmt = $mysqli->prepare("
                SELECT pm.*, u1.username as sender_username, u1.avatar as sender_avatar
                FROM private_messages pm
                JOIN users u1 ON pm.sender_id = u1.id
                WHERE ((pm.sender_id = ? AND pm.receiver_id = ?) OR (pm.sender_id = ? AND pm.receiver_id = ?))
                AND pm.id > ? AND pm.recalled = FALSE
                ORDER BY pm.id ASC
            ");
            if (!$stmt) {
                throw new Exception('私聊查询准备失败');
            }
            $stmt->bind_param("iiiii", $_SESSION['user_id'], $currentReceiverId, $currentReceiverId, $_SESSION['user_id'], $lastPrivateId);
        } else {
            // 兼容旧客户端：按 timestamp 拉取
            $stmt = $mysqli->prepare("
                SELECT pm.*, u1.username as sender_username, u1.avatar as sender_avatar
                FROM private_messages pm
                JOIN users u1 ON pm.sender_id = u1.id
                WHERE ((pm.sender_id = ? AND pm.receiver_id = ?) OR (pm.sender_id = ? AND pm.receiver_id = ?))
                AND pm.timestamp > FROM_UNIXTIME(?) AND pm.recalled = FALSE
                ORDER BY pm.timestamp ASC
            ");
            if (!$stmt) {
                throw new Exception('私聊查询准备失败');
            }
            $stmt->bind_param("iiiii", $_SESSION['user_id'], $currentReceiverId, $currentReceiverId, $_SESSION['user_id'], $lastPrivateTimestamp);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $privateReplyCache = [];
        while ($row = $result->fetch_assoc()) {
            // 检查是否为文件消息，如果是则不进行 Markdown 解析
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

            if (!empty($row['reply_to_id'])) {
                $rid = (int)$row['reply_to_id'];
                if (array_key_exists($rid, $privateReplyCache)) {
                    $row['reply_to'] = $privateReplyCache[$rid];
                } else {
                    $replyStmt = $mysqli->prepare("
                        SELECT pm.message, u1.username as sender_username
                        FROM private_messages pm
                        JOIN users u1 ON pm.sender_id = u1.id
                        WHERE pm.id = ?
                    ");
                    if ($replyStmt) {
                        $replyStmt->bind_param("i", $rid);
                        $replyStmt->execute();
                        if ($reply = $replyStmt->get_result()->fetch_assoc()) {
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
                                'username' => $reply['sender_username']
                            ];
                            $privateReplyCache[$rid] = $row['reply_to'];
                        } else {
                            $row['reply_to'] = null;
                            $privateReplyCache[$rid] = null;
                        }
                        $replyStmt->close();
                    }
                }
            }

            $row['timestamp'] = strtotime($row['timestamp']);
            $parsedPrivateMessages[] = $row;
        }
        $stmt->close();
    }

    // 3) 检查已撤回的公聊消息（仅检查当前已加载窗口范围）
    $recalledPublicIds = [];
    if ($minPublicId > 0 && $maxPublicId >= $minPublicId) {
        $stmt = $mysqli->prepare("
            SELECT id
            FROM public_messages
            WHERE id BETWEEN ? AND ?
            AND recalled = TRUE
        ");
        if (!$stmt) {
            throw new Exception('撤回公聊查询准备失败');
        }
        $stmt->bind_param("ii", $minPublicId, $maxPublicId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recalledPublicIds[] = (int)$row['id'];
        }
        $stmt->close();
    }

    // 4) 检查已撤回的私聊消息（仅检查当前已加载窗口范围）
    $recalledPrivateIds = [];
    if ($currentReceiverId > 0 && $_SESSION['user_id'] > 0 && $minPrivateId > 0 && $maxPrivateId >= $minPrivateId) {
        $stmt = $mysqli->prepare("
            SELECT id
            FROM private_messages
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            AND id BETWEEN ? AND ?
            AND recalled = TRUE
        ");
        if (!$stmt) {
            throw new Exception('撤回私聊查询准备失败');
        }
        $stmt->bind_param("iiiiii", $_SESSION['user_id'], $currentReceiverId, $currentReceiverId, $_SESSION['user_id'], $minPrivateId, $maxPrivateId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recalledPrivateIds[] = (int)$row['id'];
        }
        $stmt->close();
    }

    $mysqli->close();

    echo json_encode([
        'success' => true,
        'newPublicMessages' => $parsedPublicMessages,
        'newPrivateMessages' => $parsedPrivateMessages,
        'recalledPublicIds' => $recalledPublicIds,
        'recalledPrivateIds' => $recalledPrivateIds
    ]);
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
    echo json_encode(['success' => false, 'message' => '获取新消息失败: ' . $e->getMessage()]);
}
