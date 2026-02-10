-- VenlanChat 数据库初始化脚本
-- 此脚本会在 MySQL 容器首次启动时自动执行

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 用户表
-- ----------------------------
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

-- ----------------------------
-- 私聊消息表
-- ----------------------------
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

-- ----------------------------
-- 公聊消息表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `public_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT '发送者 ID',
    `username` VARCHAR(50) NOT NULL COMMENT '发送者用户名（冗余存储，便于展示）',
    `avatar` VARCHAR(255) DEFAULT 'default_avatar.png' COMMENT '头像路径（冗余存储，便于历史回放）',
    `message` LONGTEXT NOT NULL COMMENT '消息内容（支持 JSON 格式存储文件信息）',
    `reply_to_id` INT DEFAULT NULL COMMENT '回复的消息 ID',
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '发送时间',
    `ip` VARCHAR(45) DEFAULT NULL COMMENT '发送者 IP（可选）',
    `recalled` BOOLEAN DEFAULT FALSE COMMENT '是否已撤回',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_to_id) REFERENCES public_messages(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_recalled (recalled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公聊消息表';

SET FOREIGN_KEY_CHECKS = 1;

-- 插入初始数据（可选）
-- 管理员账号：admin / admin123（密码需要通过注册页面创建或手动 bcrypt 哈希）
-- INSERT INTO users (username, password, email, avatar) VALUES
-- ('admin', '$2y$10$...', 'admin@venlanchat.local', 'default_avatar.png');
