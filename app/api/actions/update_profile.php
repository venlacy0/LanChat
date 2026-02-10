<?php
$username = $_POST['username'] ?? $current_user['username'];
$avatar_path = $current_user['avatar'];
$mysqli = get_db_connection();
$success = false;

if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['avatar'];

    // 验证文件是否为真实图片
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        echo json_encode(['success' => false, 'message' => '无效的图片文件']);
        exit;
    }

    // 验证真实MIME类型（兼容无 fileinfo 扩展的环境）
    $mimeType = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
    if (!$mimeType && function_exists('mime_content_type')) {
        $mimeType = mime_content_type($file['tmp_name']);
    }
    if (!$mimeType && isset($imageInfo['mime'])) {
        $mimeType = $imageInfo['mime'];
    }
    if (!$mimeType) {
        echo json_encode(['success' => false, 'message' => '无法检测图片类型']);
        exit;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($mimeType, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => '不支持的图片格式']);
        exit;
    }

    // 验证文件大小
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => '图片文件过大']);
        exit;
    }

    // 使用安全的文件名
    $ext = image_type_to_extension($imageInfo[2], false);
    $new_file_name = bin2hex(random_bytes(16)) . '.' . $ext;

    // 本地存储：保存到项目根目录的 avatars/ 目录，通过 file_proxy.php 访问
    $projectRoot = realpath(__DIR__ . '/../../../');
    if ($projectRoot === false) {
        echo json_encode(['success' => false, 'message' => '服务器路径初始化失败']);
        exit;
    }

    $avatarsDir = $projectRoot . DIRECTORY_SEPARATOR . 'avatars';
    if (!is_dir($avatarsDir) && !mkdir($avatarsDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => '头像目录不可用']);
        exit;
    }

    $targetAbs = rtrim($avatarsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $new_file_name;
    if (!move_uploaded_file($file['tmp_name'], $targetAbs)) {
        echo json_encode(['success' => false, 'message' => '头像保存失败']);
        exit;
    }
    $avatar_path = 'avatars/' . $new_file_name;

    // 删除旧头像（仅删除 avatars/ 内的本地文件）
    $oldAvatar = $current_user['avatar'] ?? '';
    if (!empty($oldAvatar) &&
        !str_starts_with($oldAvatar, 'data:image/') &&
        stripos($oldAvatar, 'default_avatar.png') === false) {
        $oldRel = ltrim((string)$oldAvatar, '/\\');
        $avatarsReal = realpath($avatarsDir);
        $oldAbs = realpath($projectRoot . DIRECTORY_SEPARATOR . $oldRel);
        if ($avatarsReal !== false && $oldAbs !== false) {
            $avatarsPrefix = rtrim($avatarsReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (strpos($oldAbs, $avatarsPrefix) === 0 && is_file($oldAbs)) {
                @unlink($oldAbs);
            }
        }
    }
}

$stmt = $mysqli->prepare("UPDATE users SET username = ?, avatar = ? WHERE id = ?");
$stmt->bind_param("ssi", $username, $avatar_path, $_SESSION['user_id']);

if ($stmt->execute()) {
    $_SESSION['username'] = $username;
    logAccess("User profile updated by {$_SESSION['username']}");
    echo json_encode(['success' => true, 'message' => '个人信息更新成功', 'new_username' => $username, 'new_avatar' => $avatar_path]);
} else {
    error_log("Profile update failed: " . $mysqli->error);
    echo json_encode(['success' => false, 'message' => '更新失败: ' . $mysqli->error]);
}
$stmt->close();
$mysqli->close();
