<?php
// 更新用户最后在线时间
updateLastSeen($_SESSION['user_id']);

if (!checkRateLimit($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '发送太频繁，请稍后再试']);
    exit;
}

$message = trim($_POST['message'] ?? '');
$replyTo = intval($_POST['reply_to'] ?? 0);

// 移除控制字符，但保留换行符（\n = 0x0A）和制表符（\t = 0x09）
// 删除：0x00-0x08, 0x0B-0x1F, 0x7F（除了 \t 和 \n）
$message = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $message);

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => '消息内容不能为空']);
    exit;
}

if (strlen($message) > $config['message_max_length']) {
    echo json_encode(['success' => false, 'message' => '消息长度不能超过' . $config['message_max_length'] . '个字符']);
    exit;
}

// 验证回复消息是否存在
$replyToData = null;
$replyToId = null;
if ($replyTo > 0) {
    $mysqli = get_db_connection();
    $stmt = $mysqli->prepare("
        SELECT id, username, avatar, message
        FROM public_messages
        WHERE id = ? AND recalled = FALSE
    ");
    $stmt->bind_param("i", $replyTo);
    $stmt->execute();
    if ($result = $stmt->get_result()->fetch_assoc()) {
        $replyToData = [
            'id' => $result['id'],
            'username' => $result['username'],
            'avatar' => $result['avatar'],
            'message' => $result['message']
        ];
        $replyToId = $result['id'];
    }
    $stmt->close();
    $mysqli->close();
}

// 保存消息到数据库
$messageId = savePublicMessage([
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'avatar' => $current_user['avatar'],
    'message' => $message,
    'reply_to_id' => $replyToId,
    'timestamp' => time(),
    'ip' => getUserIP(),
    'recalled' => false
]);

if (!$messageId) {
    echo json_encode(['success' => false, 'message' => '消息保存失败']);
    exit;
}

$newMessage = [
    'id' => $messageId,
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'avatar' => $current_user['avatar'],
    'message' => customParseAllowHtml($message, $parsedown),
    'reply_to' => $replyToData,
    'timestamp' => time(),
    'ip' => getUserIP(),
    'recalled' => false
];

if ($replyToData) {
    $newMessage['reply_to']['message'] = customParseAllowHtml($replyToData['message'], $parsedown);
}

logAccess("Message sent by {$_SESSION['username']}");
echo json_encode(['success' => true, 'message' => '消息发送成功', 'new_message' => $newMessage]);
