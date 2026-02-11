<?php

/**
 * 确保必要的表存在。
 *
 * 说明：线上/部署场景最常见的问题是只初始化了 users/private_messages，遗漏 public_messages，
 * 这会导致获取消息时报错 "Table 'xxx.public_messages' doesn't exist"。
 * 这里做一次轻量的自愈：缺表时自动建表；无建表权限则记录日志并继续抛出原错误给调用方。
 */
function venlanchat_ensure_schema(mysqli $mysqli): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $tableExists = function (string $table) use ($mysqli): bool {
        // 这里 table 名是代码内固定字符串，但仍用 real_escape_string 做下兜底。
        $escaped = $mysqli->real_escape_string($table);
        try {
            $res = $mysqli->query("SHOW TABLES LIKE '{$escaped}'");
            if ($res === false) {
                return false;
            }
            $exists = $res->num_rows > 0;
            $res->free();
            return $exists;
        } catch (Throwable $e) {
            return false;
        }
    };

    // 只在确实缺表时才执行 DDL，避免每次请求都跑建表语句。
    $needUsers = !$tableExists('users');
    $needPrivate = !$tableExists('private_messages');
    $needPublic = !$tableExists('public_messages');

    if (!$needUsers && !$needPrivate && !$needPublic) {
        return;
    }

    try {
        // 用户表
        if ($needUsers) {
            $mysqli->query("
                CREATE TABLE IF NOT EXISTS `users` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
                    `password` VARCHAR(255) NOT NULL COMMENT '密码（bcrypt 哈希）',
                    `email` VARCHAR(100) NOT NULL UNIQUE COMMENT '邮箱',
                    `avatar` VARCHAR(255) DEFAULT 'default_avatar.png' COMMENT '头像路径',
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
                    `last_seen` TIMESTAMP NULL DEFAULT NULL COMMENT '最后在线时间',
                    INDEX idx_username (username),
                    INDEX idx_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';
            ");
        }

        // 私聊消息表
        if ($needPrivate) {
            $mysqli->query("
                CREATE TABLE IF NOT EXISTS `private_messages` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `sender_id` INT NOT NULL COMMENT '发送者 ID',
                    `receiver_id` INT NOT NULL COMMENT '接收者 ID',
                    `message` TEXT NOT NULL COMMENT '消息内容（支持 JSON 格式存储文件信息）',
                    `reply_to_id` INT DEFAULT NULL COMMENT '回复的消息 ID',
                    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '发送时间',
                    `recalled` BOOLEAN DEFAULT FALSE COMMENT '是否已撤回',
                    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (reply_to_id) REFERENCES private_messages(id) ON DELETE SET NULL,
                    INDEX idx_sender (sender_id),
                    INDEX idx_receiver (receiver_id),
                    INDEX idx_timestamp (timestamp)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='私聊消息表';
            ");
        }

        // 公聊消息表
        if ($needPublic) {
            $mysqli->query("
                CREATE TABLE IF NOT EXISTS `public_messages` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `username` VARCHAR(50) NOT NULL,
                    `avatar` VARCHAR(255) DEFAULT 'default_avatar.png',
                    `message` LONGTEXT NOT NULL,
                    `reply_to_id` INT DEFAULT NULL,
                    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `ip` VARCHAR(45) DEFAULT NULL,
                    `recalled` BOOLEAN DEFAULT FALSE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (reply_to_id) REFERENCES public_messages(id) ON DELETE SET NULL,
                    INDEX idx_user (user_id),
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_recalled (recalled)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公聊消息表';
            ");
        }
    } catch (Throwable $e) {
        // 不要在这里中断业务流程，交给上层按原逻辑报错；这里只做日志记录便于排查。
        error_log('[venlanchat] ensure schema failed: ' . $e->getMessage());
    }
}

function get_db_connection() {
    $config = require 'config.php';
    $db_config = $config['db'];
    
    $port = isset($db_config['port']) ? (int)$db_config['port'] : 3306;
    if ($port <= 0) {
        $port = 3306;
    }

    $mysqli = new mysqli(
        $db_config['host'],
        $db_config['user'],
        $db_config['pass'],
        $db_config['name'],
        $port
    );
    
    if ($mysqli->connect_error) {
        die("数据库连接失败: " . $mysqli->connect_error);
    }
    
    $mysqli->set_charset($db_config['charset']);
    venlanchat_ensure_schema($mysqli);
    return $mysqli;
}
?>
