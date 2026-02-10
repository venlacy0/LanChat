<?php
if (!$config['enable_file_upload']) {
    echo json_encode(['success' => false, 'message' => '文件上传功能未启用']);
    exit;
}

if (!checkRateLimit($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '发送太频繁，请稍后再试']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '文件上传失败']);
    exit;
}

$file = $_FILES['file'];

// 验证文件大小
if ($file['size'] > $config['max_file_size']) {
    $maxSizeMb = round($config['max_file_size'] / 1048576, 2);
    echo json_encode(['success' => false, 'message' => "文件大小超过限制（最大{$maxSizeMb}MB）"]);
    exit;
}

// 获取文件扩展名
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// 验证文件类型
if (!empty($config['allowed_file_types'])) {
    if (!in_array($file_ext, $config['allowed_file_types'])) {
        echo json_encode(['success' => false, 'message' => '不支持的文件类型']);
        exit;
    }
}

// 生成安全的文件名
$safe_filename = bin2hex(random_bytes(16)) . '.' . $file_ext;

// 本地上传目录
$upload_dir = $config['upload_dir'];
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 本地文件路径
$local_path = $upload_dir . $safe_filename;

// 移动上传文件到本地目录
if (!move_uploaded_file($file['tmp_name'], $local_path)) {
    echo json_encode(['success' => false, 'message' => '文件保存失败']);
    exit;
}

// 文件访问路径（相对路径）
$upload_path = $local_path;

// 获取消息内容和回复信息
$message = trim($_POST['message'] ?? '');
$replyTo = trim($_POST['reply_to'] ?? '');
$isPrivate = filter_var($_POST['is_private'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$receiverId = intval($_POST['receiver_id'] ?? 0);

// 移除控制字符，但保留换行符（\n = 0x0A）和制表符（\t = 0x09）
// 删除：0x00-0x08, 0x0B-0x1F, 0x7F（除了 \t 和 \n）
$message = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $message);

// 构建文件信息
$file_info = [
    'filename' => $file['name'],
    'safe_filename' => $safe_filename,
    'path' => $upload_path,
    'size' => $file['size'],
    'type' => $file_ext
];

if ($isPrivate && $receiverId > 0) {
    // 私聊文件消息
    $mysqli = get_db_connection();

    $replyToId = null;
    if ($replyTo) {
        $stmt = $mysqli->prepare("SELECT id FROM private_messages WHERE id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))");
        $stmt->bind_param("iiiii", $replyTo, $_SESSION['user_id'], $receiverId, $receiverId, $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $replyToId = $replyTo;
        }
        $stmt->close();
    }

    // 将文件信息编码为JSON存储在消息中
    $message_content = json_encode(['type' => 'file', 'file' => $file_info, 'text' => $message], JSON_UNESCAPED_UNICODE);

    $stmt = $mysqli->prepare("INSERT INTO private_messages (sender_id, receiver_id, message, reply_to_id, timestamp, recalled) VALUES (?, ?, ?, ?, NOW(), FALSE)");
    $stmt->bind_param("iisi", $_SESSION['user_id'], $receiverId, $message_content, $replyToId);

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
        $newMessage['timestamp'] = strtotime($newMessage['timestamp']);

        // 解析文件消息
        $newMessage['message'] = $message_content;

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
                    'message' => $reply['message'],
                    'username' => $reply['sender_username'],
                    'avatar' => $reply['sender_avatar']
                ];
            }
            $replyStmt->close();
        } else {
            $newMessage['reply_to'] = null;
        }

        logAccess("File uploaded by {$_SESSION['username']}: {$file['name']}");
        echo json_encode(['success' => true, 'new_message' => $newMessage]);
    } else {
        echo json_encode(['success' => false, 'message' => '发送失败: ' . $mysqli->error]);
    }
    $stmt->close();
    $mysqli->close();
} else {
    // 公共聊天文件消息（存储到数据库 public_messages）
    $replyToId = null;
    $replyToData = null;

    $replyToInt = intval($replyTo);
    if ($replyToInt > 0) {
        $mysqli = get_db_connection();
        $stmt = $mysqli->prepare("
            SELECT id, username, avatar, message
            FROM public_messages
            WHERE id = ? AND recalled = FALSE
        ");
        $stmt->bind_param("i", $replyToInt);
        $stmt->execute();
        if ($result = $stmt->get_result()->fetch_assoc()) {
            $replyToId = (int)$result['id'];
            $replyToData = [
                'id' => (int)$result['id'],
                'username' => $result['username'],
                'avatar' => $result['avatar'],
                'message' => $result['message']
            ];
        }
        $stmt->close();
        $mysqli->close();
    }

    $message_content = json_encode(['type' => 'file', 'file' => $file_info, 'text' => $message], JSON_UNESCAPED_UNICODE);

    $messageId = savePublicMessage([
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'avatar' => $current_user['avatar'],
        'message' => $message_content,
        'reply_to_id' => $replyToId,
        'timestamp' => time(),
        'ip' => getUserIP(),
        'recalled' => false
    ]);

    if (!$messageId) {
        @unlink($local_path);
        echo json_encode(['success' => false, 'message' => '发送失败']);
        exit;
    }

    // reply_to 仅用于预览：若被回复的是文件消息，避免做 Markdown 解析（前端会自行渲染文件卡片）
    if ($replyToData && isset($replyToData['message']) && is_string($replyToData['message'])) {
        $isReplyFileMessage = false;
        $trimmed = trim($replyToData['message']);
        if (strpos($trimmed, '{') === 0) {
            $decoded = json_decode($replyToData['message'], true);
            if ($decoded && isset($decoded['type']) && $decoded['type'] === 'file') {
                $isReplyFileMessage = true;
            }
        }
        if (!$isReplyFileMessage) {
            $replyToData['message'] = customParseAllowHtml($replyToData['message'], $parsedown);
        }
    }

    $newMessage = [
        'id' => $messageId,
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'avatar' => $current_user['avatar'],
        'message' => $message_content,
        'reply_to' => $replyToData,
        'timestamp' => time(),
        'ip' => getUserIP(),
        'recalled' => false
    ];

    logAccess("File uploaded by {$_SESSION['username']}: {$file['name']}");
    echo json_encode(['success' => true, 'message' => '文件上传成功', 'new_message' => $newMessage]);
}
