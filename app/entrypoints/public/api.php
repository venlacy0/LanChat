<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/security.php';
require_once __DIR__ . '/../../helpers/logger.php';
require_once __DIR__ . '/../../helpers/ip.php';
require_once __DIR__ . '/../../helpers/rate_limiter.php';
require_once __DIR__ . '/../../services/messages.php';
require_once __DIR__ . '/../../services/user.php';
require_once __DIR__ . '/../../services/settings.php';

// Ensure logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$mysqli = get_db_connection();
$current_user = getCurrentUser($mysqli, $_SESSION['user_id']);
if (!$current_user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}
$current_user['avatar'] = ensureAvatar($current_user['avatar'] ?? null);
$_SESSION['username'] = $current_user['username'];
$mysqli->close();

// 处理AJAX请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // 验证CSRF令牌
    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || !hash_equals($sessionToken, (string)$token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    $handlers = [
        'update_profile' => __DIR__ . '/../../api/actions/update_profile.php',
        'send_message' => __DIR__ . '/../../api/actions/send_message.php',
        'get_messages' => __DIR__ . '/../../api/actions/get_messages.php',
        'send_private_message' => __DIR__ . '/../../api/actions/send_private_message.php',
        'get_private_messages' => __DIR__ . '/../../api/actions/get_private_messages.php',
        'recall_message' => __DIR__ . '/../../api/actions/recall_message.php',
        'check_new_messages' => __DIR__ . '/../../api/actions/check_new_messages.php',
        'get_file_preview' => __DIR__ . '/../../api/actions/get_file_preview.php',
        'get_settings' => __DIR__ . '/../../api/actions/get_settings.php',
        'save_settings' => __DIR__ . '/../../api/actions/save_settings.php',
        'upload_file' => __DIR__ . '/../../api/actions/upload_file.php'
    ];

    if (!isset($handlers[$action])) {
        exit;
    }

    require $handlers[$action];
    exit;
}
