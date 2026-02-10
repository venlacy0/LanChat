<?php
// 用户设置存储为 JSON 文件 (data/settings_{user}.json)

// 旧 theme 索引到新 hue/mode 的映射表
const THEME_MIGRATION_MAP = [
    ['hue' => 217, 'mode' => 'light'],  // 0: 默认白天
    ['hue' => 217, 'mode' => 'dark'],   // 1: 默认夜晚
    ['hue' => 199, 'mode' => 'light'],  // 2: 蓝色白天
    ['hue' => 199, 'mode' => 'dark'],   // 3: 蓝色夜晚
    ['hue' => 142, 'mode' => 'light'],  // 4: 绿色白天
    ['hue' => 142, 'mode' => 'dark'],   // 5: 绿色夜晚
    ['hue' => 263, 'mode' => 'light'],  // 6: 紫色白天
    ['hue' => 263, 'mode' => 'dark'],   // 7: 紫色夜晚
    ['hue' => 25, 'mode' => 'light'],   // 8: 橙色白天
    ['hue' => 25, 'mode' => 'dark'],    // 9: 橙色夜晚
];

// 将旧格式设置迁移为新格式
function migrateSettings(array $settings): array
{
    // 已经是新格式（包含 hue 和 mode 字段）
    if (isset($settings['hue']) && isset($settings['mode'])) {
        return $settings;
    }

    // 旧格式：包含 theme 字段，需要迁移
    if (isset($settings['theme'])) {
        $themeIndex = intval($settings['theme']);
        $mapped = THEME_MIGRATION_MAP[$themeIndex] ?? THEME_MIGRATION_MAP[0];
        return [
            'hue' => $mapped['hue'],
            'mode' => $mapped['mode'],
            'radius' => $settings['radius'] ?? 20
        ];
    }

    // 未知格式，返回默认值
    return ['hue' => 217, 'mode' => 'light', 'radius' => 20];
}

function getUserSettings($user_id): array
{
    $default = ['hue' => 217, 'mode' => 'light', 'radius' => 20];

    if (!is_numeric($user_id) || $user_id <= 0) {
        return $default;
    }
    $user_id = intval($user_id);

    $settingsFile = __DIR__ . "/../../data/settings_$user_id.json";

    $realPath = realpath(dirname($settingsFile));
    $dataPath = realpath(__DIR__ . '/../../data');
    if ($realPath === false || $dataPath === false || $realPath !== $dataPath) {
        return $default;
    }

    if (file_exists($settingsFile)) {
        $content = file_get_contents($settingsFile);
        $settings = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($settings)) {
            // 自动迁移旧格式
            $migrated = migrateSettings($settings);
            // 如果发生了迁移，持久化新格式
            if (!isset($settings['hue']) && isset($settings['theme'])) {
                file_put_contents($settingsFile, json_encode($migrated), LOCK_EX);
            }
            return $migrated;
        }
    }
    return $default;
}

function saveUserSettings($user_id, $settings): bool
{
    if (!is_numeric($user_id) || $user_id <= 0) {
        return false;
    }
    $user_id = intval($user_id);

    if (!is_array($settings)) {
        return false;
    }

    $settingsFile = __DIR__ . "/../../data/settings_$user_id.json";

    $realPath = realpath(dirname($settingsFile));
    $dataPath = realpath(__DIR__ . '/../../data');
    if ($realPath === false || $dataPath === false || $realPath !== $dataPath) {
        return false;
    }

    return file_put_contents($settingsFile, json_encode($settings), LOCK_EX) !== false;
}
