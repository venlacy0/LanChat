<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$path = $_GET['path'] ?? '';
if (!is_string($path) || $path === '') {
    http_response_code(400);
    exit;
}
// Force relative paths inside project root
$path = ltrim($path, "/\\");

$projectRoot = realpath(__DIR__ . '/../../../');
if ($projectRoot === false) {
    http_response_code(500);
    exit;
}

$rawPath = $projectRoot . DIRECTORY_SEPARATOR . $path;
$realPath = realpath($rawPath);
$uploadsPath = realpath($projectRoot . DIRECTORY_SEPARATOR . 'uploads');
$avatarsPath = realpath($projectRoot . DIRECTORY_SEPARATOR . 'avatars');

if ($realPath === false || ($uploadsPath === false && $avatarsPath === false)) {
    http_response_code(404);
    exit;
}

$isAllowed = false;
$uploadsPrefix = $uploadsPath !== false ? rtrim($uploadsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : null;
$avatarsPrefix = $avatarsPath !== false ? rtrim($avatarsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : null;

if ($uploadsPrefix !== null && strpos($realPath, $uploadsPrefix) === 0) {
    $isAllowed = true;
}
if ($avatarsPrefix !== null && strpos($realPath, $avatarsPrefix) === 0) {
    $isAllowed = true;
}

if (!$isAllowed || !is_file($realPath)) {
    http_response_code(403);
    exit;
}

$mime = null;
if (function_exists('mime_content_type')) {
    $mime = mime_content_type($realPath);
}
if (!$mime) {
    $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'svg' => 'image/svg+xml',
        'txt' => 'text/plain; charset=utf-8',
        'md' => 'text/plain; charset=utf-8',
        'pdf' => 'application/pdf',
    ];
    $mime = $map[$ext] ?? 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: private, max-age=600');
readfile($realPath);
