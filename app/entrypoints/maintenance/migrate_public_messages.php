<?php
/**
 * 公聊消息迁移脚本
 * 将 data/messages.json 中的消息迁移到 public_messages 表
 *
 * 使用方法：
 * 1. 访问 http://your-domain/migrate_public_messages.php
 * 2. 输入管理员密码进行认证
 * 3. 点击"开始迁移"按钮
 */

// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 加载配置和数据库连接
require_once '../../config.php';
require_once '../../db_connect.php';

$migration_complete = false;
$migration_results = [];
$has_error = false;
$authenticated = false;

// 处理认证
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    if ($_POST['admin_password'] === $config['admin_password']) {
        $authenticated = true;
    } else {
        $migration_results[] = ['success' => false, 'message' => '管理员密码错误'];
        $has_error = true;
    }
}

// 执行迁移
if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate'])) {
    try {
        // 1. 读取 JSON 文件
        $json_file = '../../data/messages.json';
        if (!file_exists($json_file)) {
            $migration_results[] = ['success' => false, 'message' => '消息文件不存在: ' . $json_file];
            $has_error = true;
        } else {
            $json_content = file_get_contents($json_file);
            $messages = json_decode($json_content, true);

            if (!is_array($messages)) {
                $migration_results[] = ['success' => false, 'message' => 'JSON 文件格式错误'];
                $has_error = true;
            } else {
                $migration_results[] = ['success' => true, 'message' => '读取 JSON 文件成功，共 ' . count($messages) . ' 条消息'];

                // 2. 连接数据库
                $mysqli = get_db_connection();

                // 3. 清空现有的公聊消息表（可选）
                $clear_result = $mysqli->query("TRUNCATE TABLE public_messages");
                if ($clear_result) {
                    $migration_results[] = ['success' => true, 'message' => '已清空现有公聊消息表'];
                } else {
                    $migration_results[] = ['success' => false, 'message' => '清空表失败: ' . $mysqli->error];
                    $has_error = true;
                }

                // 4. 迁移消息
                if (!$has_error) {
                    $migrated_count = 0;
                    $failed_count = 0;

                    // 准备预处理语句
                    $stmt = $mysqli->prepare("
                        INSERT INTO public_messages
                        (user_id, username, avatar, message, reply_to_id, timestamp, ip, recalled)
                        VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?, ?)
                    ");

                    if (!$stmt) {
                        $migration_results[] = ['success' => false, 'message' => '准备语句失败: ' . $mysqli->error];
                        $has_error = true;
                    } else {
                        foreach ($messages as $msg) {
                            // 处理 reply_to 字段
                            $reply_to_id = null;
                            if (!empty($msg['reply_to'])) {
                                // 如果 reply_to 是对象，需要查找对应的消息 ID
                                // 这里暂时设为 NULL，因为 JSON 中的 reply_to 是对象而不是 ID
                                $reply_to_id = null;
                            }

                            $user_id = intval($msg['user_id'] ?? 0);
                            $username = $msg['username'] ?? 'Unknown';
                            $avatar = $msg['avatar'] ?? 'default_avatar.png';
                            $message = $msg['message'] ?? '';
                            $timestamp = intval($msg['timestamp'] ?? time());
                            $ip = $msg['ip'] ?? '0.0.0.0';
                            $recalled = $msg['recalled'] ? 1 : 0;

                            $stmt->bind_param(
                                'isssiisi',
                                $user_id,
                                $username,
                                $avatar,
                                $message,
                                $reply_to_id,
                                $timestamp,
                                $ip,
                                $recalled
                            );

                            if ($stmt->execute()) {
                                $migrated_count++;
                            } else {
                                $failed_count++;
                                $migration_results[] = [
                                    'success' => false,
                                    'message' => '迁移消息失败 (ID: ' . ($msg['id'] ?? 'unknown') . '): ' . $stmt->error
                                ];
                            }
                        }

                        $stmt->close();

                        $migration_results[] = [
                            'success' => true,
                            'message' => '迁移完成！成功: ' . $migrated_count . ' 条，失败: ' . $failed_count . ' 条'
                        ];
                    }
                }

                $mysqli->close();
            }
        }

        $migration_complete = true;
    } catch (Exception $e) {
        $migration_results[] = ['success' => false, 'message' => '迁移异常: ' . $e->getMessage()];
        $has_error = true;
        $migration_complete = true;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>公聊消息迁移工具</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #eef1f5;
            --text-color: #1a202c;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --accent-color: #4299e1;
            --success-color: #48bb78;
            --error-color: #f56565;
            --shadow-color: rgba(0,0,0,0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--bg-color);
            min-height: 100vh;
            padding: 20px;
            color: var(--text-color);
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
        }

        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px var(--shadow-color);
            margin-bottom: 20px;
        }

        h1 {
            text-align: center;
            color: var(--accent-color);
            margin-bottom: 10px;
            font-size: 2rem;
            font-weight: 600;
        }

        .subtitle {
            text-align: center;
            color: #718096;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.95rem;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 5px rgba(66, 153, 225, 0.3);
        }

        button {
            width: 100%;
            padding: 14px;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background: #3182ce;
        }

        .result-item {
            padding: 12px 16px;
            margin-bottom: 10px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .result-item.success {
            background: #f0fff4;
            border-left: 4px solid var(--success-color);
            color: #22543d;
        }

        .result-item.error {
            background: #fff5f5;
            border-left: 4px solid var(--error-color);
            color: #742a2a;
        }

        .result-item i {
            font-size: 1.1rem;
        }

        .success-icon {
            color: var(--success-color);
        }

        .error-icon {
            color: var(--error-color);
        }

        .warning {
            background: #fffaf0;
            border-left: 4px solid #ed8936;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #7c2d12;
        }

        .warning strong {
            color: #c05621;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1><i class="fas fa-database"></i> 公聊消息迁移</h1>
            <p class="subtitle">将 JSON 文件中的消息迁移到数据库</p>

            <div class="warning">
                <strong><i class="fas fa-exclamation-triangle"></i> 注意:</strong><br>
                此操作将清空现有的公聊消息表，然后导入 JSON 文件中的所有消息。请确保已备份重要数据。
            </div>

            <?php if (!$migration_complete): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="admin_password"><i class="fas fa-lock"></i> 管理员密码</label>
                        <input type="password" id="admin_password" name="admin_password" required placeholder="输入管理员密码">
                    </div>

                    <?php if ($has_error && !$authenticated): ?>
                        <div class="result-item error">
                            <i class="fas fa-times-circle error-icon"></i>
                            <span>密码错误，请重试</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($authenticated): ?>
                        <input type="hidden" name="migrate" value="1">
                        <button type="submit">
                            <i class="fas fa-play"></i> 开始迁移
                        </button>
                    <?php else: ?>
                        <button type="submit">
                            <i class="fas fa-check"></i> 验证密码
                        </button>
                    <?php endif; ?>
                </form>
            <?php else: ?>
                <div class="results">
                    <h2 style="margin-bottom: 20px; color: var(--accent-color);">
                        <i class="fas fa-list-check"></i> 迁移结果
                    </h2>
                    <?php foreach ($migration_results as $result): ?>
                        <div class="result-item <?php echo $result['success'] ? 'success' : 'error'; ?>">
                            <i class="fas <?php echo $result['success'] ? 'fa-check-circle success-icon' : 'fa-times-circle error-icon'; ?>"></i>
                            <span><?php echo htmlspecialchars($result['message']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 30px; text-align: center;">
                    <a href="migrate_public_messages.php" style="display: inline-block; padding: 12px 24px; background: var(--accent-color); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                        <i class="fas fa-redo"></i> 返回
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
