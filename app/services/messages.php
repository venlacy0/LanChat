<?php
// Public message storage helpers (Database)

/**
 * 获取公聊消息（从数据库）
 * 返回最新的消息列表（按时间戳降序）
 */
function getPublicMessages(): array
{
    if (isset($GLOBALS['__public_messages_cache'])) {
        return $GLOBALS['__public_messages_cache'];
    }

    try {
        $mysqli = get_db_connection();

        // 查询所有未撤回的公聊消息，按时间戳降序排列
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
            WHERE recalled = FALSE
            ORDER BY timestamp DESC
            LIMIT 10000
        ");

        if (!$stmt) {
            $mysqli->close();
            return [];
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];

        while ($row = $result->fetch_assoc()) {
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
                        'id' => $row['reply_to_id'],
                        'username' => $reply_result['username'],
                        'avatar' => $reply_result['avatar'],
                        'message' => $reply_result['message']
                    ];
                }
                $reply_stmt->close();
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

        $GLOBALS['__public_messages_cache'] = $messages;
        return $messages;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * 保存新的公聊消息到数据库
 */
function savePublicMessage(array $messageData): ?int
{
    try {
        $mysqli = get_db_connection();

        $stmt = $mysqli->prepare("
            INSERT INTO public_messages
            (user_id, username, avatar, message, reply_to_id, timestamp, ip, recalled)
            VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?, ?)
        ");

        if (!$stmt) {
            $mysqli->close();
            return null;
        }

        $user_id = $messageData['user_id'];
        $username = $messageData['username'];
        $avatar = $messageData['avatar'];
        $message = $messageData['message'];
        $reply_to_id = $messageData['reply_to_id'] ?? null;
        $timestamp = $messageData['timestamp'] ?? time();
        $ip = $messageData['ip'] ?? '0.0.0.0';
        $recalled = $messageData['recalled'] ? 1 : 0;

        $stmt->bind_param(
            'isssiisi',
            $user_id,
            $username,
            $avatar,
            $message,
            $reply_to_id,
            $timestamp,
            $ip,
            $recalled
        );

        if ($stmt->execute()) {
            $message_id = $mysqli->insert_id;
            $stmt->close();
            $mysqli->close();

            // 清除缓存
            unset($GLOBALS['__public_messages_cache']);

            return $message_id;
        }

        $stmt->close();
        $mysqli->close();
        return null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * 撤回公聊消息
 */
function recallPublicMessage(int $messageId): bool
{
    try {
        $mysqli = get_db_connection();

        $stmt = $mysqli->prepare("
            UPDATE public_messages
            SET recalled = TRUE
            WHERE id = ?
        ");

        if (!$stmt) {
            $mysqli->close();
            return false;
        }

        $stmt->bind_param("i", $messageId);
        $result = $stmt->execute();
        $stmt->close();
        $mysqli->close();

        // 清除缓存
        unset($GLOBALS['__public_messages_cache']);

        return $result;
    } catch (Exception $e) {
        return false;
    }
}

// Placeholder to avoid runtime errors; implement DB-backed read tracking later if needed.
function markPublicMessagesAsRead(int $userId): void
{
    // no-op
}
