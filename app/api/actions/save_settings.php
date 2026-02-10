<?php
// 接收 hue (0-360) 和 mode (light/dark) 替代旧的 theme 索引
$hue = intval($_POST['hue'] ?? 217);
$mode = $_POST['mode'] ?? 'light';
$radius = intval($_POST['radius'] ?? 20);

// 校验 hue 范围
if ($hue < 0 || $hue > 360) {
    $hue = 217;
}

// 校验 mode 值
if ($mode !== 'light' && $mode !== 'dark') {
    $mode = 'light';
}

// 校验 radius 范围
if ($radius < 0 || $radius > 50) {
    $radius = 20;
}

$settings = ['hue' => $hue, 'mode' => $mode, 'radius' => $radius];
saveUserSettings($_SESSION['user_id'], $settings);
echo json_encode(['success' => true, 'message' => '设置保存成功']);
