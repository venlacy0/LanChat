<?php
// VenlanChat 配置文件
return [
    // 数据库配置
    'db' => [
        'host' => getenv('DB_HOST') ?: 'db',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'user' => getenv('DB_USER') ?: 'venlanchat_user',
        'pass' => getenv('DB_PASS') ?: 'venlanchat_pass_2026',
        'name' => getenv('DB_NAME') ?: 'venlanchat',
        'charset' => 'utf8mb4'
    ],

    // 消息相关配置
    'log_file' => 'data/log.txt',
    'message_max_length' => 50000,
    'rate_limit' => 50,

    // 安全配置
    'admin_password' => 'admin123',
    'enable_admin_delete' => true,
    'enable_rate_limit' => true,
    'enable_access_log' => true,
    'enable_emoji' => true,

    // 站点配置
    'site_title' => 'VenlanChat',
    'site_description' => '实时聊天室',

    // 显示配置
    'auto_refresh_interval' => 5000,
    'show_timestamp' => true,
    'show_ip_to_admin' => true,

    // 文件上传配置
    'enable_file_upload' => true,
    'max_file_size' => 4194304, // 4MB
    'allowed_file_types' => [], // 留空表示允许所有类型
    'upload_dir' => 'uploads/',

    // 系统配置
    'timezone' => 'Asia/Shanghai',
    'date_format' => 'Y-m-d H:i:s',

    // 主题配置
    'theme' => [
        'primary_color' => '#3b82f6',
        'secondary_color' => '#334155',
        'background_type' => 'solid',
        'custom_css' => '',
    ],

    // 版本信息
    'version' => '2.0',
    'build_date' => '2026-02-10',
];
?>
