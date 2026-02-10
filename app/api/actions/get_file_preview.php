<?php
// 获取文件预览内容
$filePath = $_POST['file_path'] ?? '';

if (!is_string($filePath) || trim($filePath) === '') {
    echo json_encode(['success' => false, 'message' => '文件路径无效']);
    exit;
}

$filePath = ltrim(trim($filePath), "/\\");
$fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

// 只允许预览 txt 和 md 文件
if (!in_array($fileExt, ['txt', 'md'])) {
    echo json_encode(['success' => false, 'message' => '不支持的文件类型预览']);
    exit;
}

$maxSize = 1024 * 1024; // 1MB

// 仅支持本地 uploads/ 内的文件预览
$projectRoot = realpath(__DIR__ . '/../../../');
if ($projectRoot === false) {
    echo json_encode(['success' => false, 'message' => '服务器路径初始化失败']);
    exit;
}

$uploadsDir = $config['upload_dir'] ?? 'uploads';
$uploadsDir = trim((string)$uploadsDir, "/\\");
if ($uploadsDir === '') {
    $uploadsDir = 'uploads';
}

$uploadsAbs = realpath($projectRoot . DIRECTORY_SEPARATOR . $uploadsDir);
$realPath = realpath($projectRoot . DIRECTORY_SEPARATOR . $filePath);

// 确保文件在 uploads 目录内
if ($realPath === false || $uploadsAbs === false) {
    echo json_encode(['success' => false, 'message' => '文件不存在']);
    exit;
}
$uploadsPrefix = rtrim($uploadsAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strpos($realPath, $uploadsPrefix) !== 0 || !is_file($realPath)) {
    echo json_encode(['success' => false, 'message' => '文件路径不安全']);
    exit;
}

// 检查文件大小，防止加载过大文件
$fileSize = filesize($realPath);
if ($fileSize === false || $fileSize > $maxSize) {
    echo json_encode(['success' => false, 'message' => '文件过大，无法预览']);
    exit;
}

// 读取文件内容
$content = file_get_contents($realPath);
if ($content === false) {
    echo json_encode(['success' => false, 'message' => '无法读取文件']);
    exit;
}

// 处理 Markdown 文件
if ($fileExt === 'md') {
    $content = customParse($content, $parsedown);
} else {
    // TXT 文件进行 HTML 转义和换行转换
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $content = nl2br($content);
}

echo json_encode(['success' => true, 'content' => $content, 'type' => $fileExt]);
