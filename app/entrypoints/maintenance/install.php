<?php
/**
 * VenlanChat 安装程序
 * 用于初始化数据库、创建必要的目录和配置文件
 */

// 开启错误报告(安装完成后应删除此文件)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 默认配置
$default_config = [
    'db' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',
        'name' => 'venlanchat',
        'charset' => 'utf8mb4'
    ],
    'log_file' => 'data/log.txt',
    'db_file' => 'data/messages.json',
    'rate_limit' => 50,
    'message_max_length' => 50000,
    'max_messages' => 100,
    'admin_password' => 'admin123',
    'enable_admin_delete' => true,
    'enable_rate_limit' => true,
    'enable_access_log' => true,
    'enable_emoji' => true,
    'site_title' => 'VenlanChat',
    'site_description' => '实时聊天室',
    'auto_refresh_interval' => 5000,
    'show_timestamp' => true,
    'show_ip_to_admin' => true,
    'enable_file_upload' => false,
    'max_file_size' => 4194304,
    'allowed_file_types' => [],
    'timezone' => 'Asia/Shanghai',
    'date_format' => 'Y-m-d H:i:s',
    'theme' => [
        // 避免蓝紫渐变与玻璃拟态风格：默认使用克制的纯色主题
        'primary_color' => '#3b82f6',
        'secondary_color' => '#334155',
        'background_type' => 'solid',
        'custom_css' => '',
    ],
    'version' => '2.0',
    'build_date' => date('Y-m-d'),
];

// Parsedown库的下载URL
$parsedown_url = 'https://raw.githubusercontent.com/erusev/parsedown/master/Parsedown.php';

/**
 * 创建目录
 */
function create_directory($path, $permissions = 0755) {
    if (!is_dir($path)) {
        if (!mkdir($path, $permissions, true)) {
            return ['success' => false, 'message' => "无法创建目录: $path"];
        }
    }
    return ['success' => true, 'message' => "目录创建成功: $path"];
}

/**
 * 创建文件
 */
function create_file($filename, $content) {
    if (file_exists($filename)) {
        return ['success' => false, 'message' => "文件已存在: $filename"];
    }
    
    if (file_put_contents($filename, $content) === false) {
        return ['success' => false, 'message' => "无法创建文件: $filename"];
    }
    return ['success' => true, 'message' => "文件创建成功: $filename"];
}

/**
 * 下载Parsedown库
 */
function download_parsedown($url, $destination) {
    $content = @file_get_contents($url);
    if ($content === false) {
        return ['success' => false, 'message' => "无法下载 Parsedown.php，请手动下载"];
    }
    
    if (file_put_contents($destination, $content) === false) {
        return ['success' => false, 'message' => "无法保存 Parsedown.php"];
    }
    return ['success' => true, 'message' => "Parsedown.php 下载成功"];
}

/**
 * 测试数据库连接
 */
function test_db_connection($host, $user, $pass) {
    $mysqli = @new mysqli($host, $user, $pass);
    if ($mysqli->connect_error) {
        return ['success' => false, 'message' => "数据库连接失败: " . $mysqli->connect_error];
    }
    $mysqli->close();
    return ['success' => true, 'message' => "数据库连接测试成功"];
}

// 处理安装请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $results = [];
    $has_error = false;
    
    // 获取数据库配置
    $db_host = $_POST['db_host'] ?? $default_config['db']['host'];
    $db_user = $_POST['db_user'] ?? $default_config['db']['user'];
    $db_pass = $_POST['db_pass'] ?? $default_config['db']['pass'];
    $db_name = $_POST['db_name'] ?? $default_config['db']['name'];
    
    // 测试数据库连接
    $test_result = test_db_connection($db_host, $db_user, $db_pass);
    $results[] = $test_result;
    if (!$test_result['success']) {
        $has_error = true;
    }
    
    if (!$has_error) {
        // 1. 创建必要的目录
        $directories = ['avatars', 'data', 'logs', 'lib'];
        foreach ($directories as $dir) {
            $result = create_directory($dir);
            $results[] = $result;
            if (!$result['success']) {
                $has_error = true;
            }
        }
        
        // 2. 创建 config.php
        $config_content = "<?php\n// VenlanChat 配置文件\nreturn " . var_export([
            'db' => [
                'host' => $db_host,
                'user' => $db_user,
                'pass' => $db_pass,
                'name' => $db_name,
                'charset' => $default_config['db']['charset']
            ],
            'db_file' => $default_config['db_file'],
            'log_file' => $default_config['log_file'],
            'max_messages' => $default_config['max_messages'],
            'message_max_length' => $default_config['message_max_length'],
            'rate_limit' => $default_config['rate_limit'],
            'admin_password' => $default_config['admin_password'],
            'enable_admin_delete' => $default_config['enable_admin_delete'],
            'enable_rate_limit' => $default_config['enable_rate_limit'],
            'enable_access_log' => $default_config['enable_access_log'],
            'enable_emoji' => $default_config['enable_emoji'],
            'site_title' => $default_config['site_title'],
            'site_description' => $default_config['site_description'],
            'auto_refresh_interval' => $default_config['auto_refresh_interval'],
            'show_timestamp' => $default_config['show_timestamp'],
            'show_ip_to_admin' => $default_config['show_ip_to_admin'],
            'enable_file_upload' => $default_config['enable_file_upload'],
            'max_file_size' => $default_config['max_file_size'],
            'allowed_file_types' => $default_config['allowed_file_types'],
            'timezone' => $default_config['timezone'],
            'date_format' => $default_config['date_format'],
            'theme' => $default_config['theme'],
            'version' => $default_config['version'],
            'build_date' => $default_config['build_date'],
        ], true) . ";\n?>";
        
        $result = create_file('config.php', $config_content);
        $results[] = $result;
        if (!$result['success']) {
            $has_error = true;
        }
        
        // 3. 创建 db_connect.php
        $db_connect_content = <<<'EOD'
<?php
function get_db_connection() {
    $config = require 'config.php';
    $db_config = $config['db'];
    
    $mysqli = new mysqli(
        $db_config['host'],
        $db_config['user'],
        $db_config['pass'],
        $db_config['name']
    );
    
    if ($mysqli->connect_error) {
        die("数据库连接失败: " . $mysqli->connect_error);
    }
    
    $mysqli->set_charset($db_config['charset']);
    return $mysqli;
}
?>
EOD;
        
        $result = create_file('db_connect.php', $db_connect_content);
        $results[] = $result;
        if (!$result['success']) {
            $has_error = true;
        }
        
        // 4. 创建 messages.json
        $result = create_file('data/messages.json', '[]');
        $results[] = $result;
        
        // 5. 下载 Parsedown.php
        if (!file_exists('lib/Parsedown.php')) {
            $result = download_parsedown($parsedown_url, 'lib/Parsedown.php');
            $results[] = $result;
        } else {
            $results[] = ['success' => true, 'message' => 'Parsedown.php 已存在'];
        }
        
        // 6. 创建数据库和表
        if (!$has_error) {
            $mysqli = new mysqli($db_host, $db_user, $db_pass);
            
            // 创建数据库
            $sql = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            if ($mysqli->query($sql)) {
                $results[] = ['success' => true, 'message' => "数据库 $db_name 创建成功"];
            } else {
                $results[] = ['success' => false, 'message' => "创建数据库失败: " . $mysqli->error];
                $has_error = true;
            }
            
            // 选择数据库
            $mysqli->select_db($db_name);
            
            // 创建 users 表
            $users_table = "
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    email VARCHAR(100) NOT NULL UNIQUE,
                    avatar VARCHAR(255) DEFAULT 'default_avatar.png',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_seen TIMESTAMP NULL DEFAULT NULL,
                    INDEX idx_username (username),
                    INDEX idx_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            
            if ($mysqli->query($users_table)) {
                $results[] = ['success' => true, 'message' => '用户表创建成功'];
            } else {
                $results[] = ['success' => false, 'message' => '创建用户表失败: ' . $mysqli->error];
                $has_error = true;
            }
            
            // 创建 private_messages 表
            $private_messages_table = "
                CREATE TABLE IF NOT EXISTS private_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sender_id INT NOT NULL,
                    receiver_id INT NOT NULL,
                    message TEXT NOT NULL,
                    reply_to_id INT DEFAULT NULL,
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    recalled BOOLEAN DEFAULT FALSE,
                    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (reply_to_id) REFERENCES private_messages(id) ON DELETE SET NULL,
                    INDEX idx_sender (sender_id),
                    INDEX idx_receiver (receiver_id),
                    INDEX idx_timestamp (timestamp)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            
            if ($mysqli->query($private_messages_table)) {
                $results[] = ['success' => true, 'message' => '私聊消息表创建成功'];
            } else {
                $results[] = ['success' => false, 'message' => '创建私聊消息表失败: ' . $mysqli->error];
                $has_error = true;
            }

            // 创建 public_messages 表
            $public_messages_table = "
                CREATE TABLE IF NOT EXISTS public_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    username VARCHAR(50) NOT NULL,
                    avatar VARCHAR(255) DEFAULT 'default_avatar.png',
                    message LONGTEXT NOT NULL,
                    reply_to_id INT DEFAULT NULL,
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    ip VARCHAR(45) DEFAULT NULL,
                    recalled BOOLEAN DEFAULT FALSE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (reply_to_id) REFERENCES public_messages(id) ON DELETE SET NULL,
                    INDEX idx_user (user_id),
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_recalled (recalled)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";

            if ($mysqli->query($public_messages_table)) {
                $results[] = ['success' => true, 'message' => '公聊消息表创建成功'];
            } else {
                $results[] = ['success' => false, 'message' => '创建公聊消息表失败: ' . $mysqli->error];
                $has_error = true;
            }

            $mysqli->close();
        }
        
        // 7. 创建 .htaccess (可选)
        $htaccess_content = "
# 禁止目录列表
Options -Indexes

# 保护敏感文件
<FilesMatch \"(config\\.php|db_connect\\.php|\\.json|\\.log|\\.txt)$\">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# 允许访问 index.php
<Files \"index.php\">
    Allow from all
</Files>

# 允许访问登录注册页面
<FilesMatch \"(login\\.php|register\\.php|logout\\.php)$\">
    Allow from all
</FilesMatch>

# URL重写(如果需要)
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
</IfModule>
";
        
        if (!file_exists('.htaccess')) {
            $result = create_file('.htaccess', $htaccess_content);
            $results[] = $result;
        }
    }
    
    // 显示结果
    $install_complete = !$has_error;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VenlanChat 安装向导</title>
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
            --warning-color: #ed8936;
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
            font-size: 2.25rem;
            font-weight: 600;
        }

        .subtitle {
            text-align: center;
            color: #718096;
            margin-bottom: 30px;
            font-size: 1rem;
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

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            background: var(--card-bg);
        }

        input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 5px rgba(66, 153, 225, 0.3);
        }

        .hint {
            font-size: 0.85rem;
            color: #718096;
            margin-top: 6px;
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

        .complete-message {
            text-align: center;
            padding: 30px;
            background: #f0fff4;
            border-radius: 12px;
            margin-top: 20px;
        }

        .complete-message h2 {
            color: var(--success-color);
            margin-bottom: 15px;
            font-size: 1.75rem;
            font-weight: 600;
        }

        .complete-message p {
            color: #2d3748;
            line-height: 1.8;
            margin-bottom: 10px;
        }

        .action-links {
            margin-top: 25px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .action-links a {
            padding: 12px 28px;
            background: var(--accent-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        .action-links a:hover {
            background: #3182ce;
        }

        .warning {
            background: #fffaf0;
            border-left: 4px solid var(--warning-color);
            padding: 16px 20px;
            border-radius: 8px;
            margin-top: 20px;
            color: #7c2d12;
        }

        .warning strong {
            color: #c05621;
        }

        .warning code {
            background: #fef5e7;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }

        @media (max-width: 600px) {
            .card {
                padding: 30px 20px;
            }
            h1 {
                font-size: 1.875rem;
            }
            .action-links {
                flex-direction: column;
            }
            .action-links a {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1><i class="fas fa-rocket"></i> VenlanChat</h1>
            <p class="subtitle">欢迎使用 VenlanChat 安装向导</p>

            <?php if (!isset($install_complete)): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="db_host"><i class="fas fa-server"></i> 数据库主机</label>
                        <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($default_config['db']['host']); ?>" required>
                        <p class="hint">通常为 localhost 或 127.0.0.1</p>
                    </div>

                    <div class="form-group">
                        <label for="db_user"><i class="fas fa-user"></i> 数据库用户名</label>
                        <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($default_config['db']['user']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="db_pass"><i class="fas fa-lock"></i> 数据库密码</label>
                        <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($default_config['db']['pass']); ?>">
                        <p class="hint">如果没有密码请留空</p>
                    </div>

                    <div class="form-group">
                        <label for="db_name"><i class="fas fa-database"></i> 数据库名称</label>
                        <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($default_config['db']['name']); ?>" required>
                        <p class="hint">将自动创建此数据库(如果不存在)</p>
                    </div>

                    <button type="submit">
                        <i class="fas fa-magic"></i> 开始安装
                    </button>
                </form>
            <?php else: ?>
                <div class="results">
                    <h2 style="margin-bottom: 20px; color: var(--accent-color);">
                        <i class="fas fa-list-check"></i> 安装进度
                    </h2>
                    <?php foreach ($results as $result): ?>
                        <div class="result-item <?php echo $result['success'] ? 'success' : 'error'; ?>">
                            <i class="fas <?php echo $result['success'] ? 'fa-check-circle success-icon' : 'fa-times-circle error-icon'; ?>"></i>
                            <span><?php echo htmlspecialchars($result['message']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($install_complete): ?>
                    <div class="complete-message">
                        <h2><i class="fas fa-check-circle"></i> 安装成功!</h2>
                        <p>VenlanChat 已成功安装并配置完成。</p>
                        <p>您现在可以访问聊天室或注册新用户。</p>
                        
                        <div class="action-links">
                            <a href="register.php"><i class="fas fa-user-plus"></i> 注册账户</a>
                            <a href="login.php"><i class="fas fa-sign-in-alt"></i> 登录</a>
                        </div>
                    </div>

                    <div class="warning">
                        <strong><i class="fas fa-exclamation-triangle"></i> 重要提示:</strong><br>
                        为了安全起见,请立即删除 <code>install.php</code> 文件!<br>
                        默认管理员密码为: <code>admin123</code>,请在 <code>config.php</code> 中修改。
                    </div>
                <?php else: ?>
                    <div class="warning">
                        <strong><i class="fas fa-exclamation-triangle"></i> 安装失败</strong><br>
                        请检查上述错误信息并重新尝试安装。
                    </div>
                    <div style="margin-top: 20px;">
                        <a href="install.php" style="display: inline-block; padding: 12px 24px; background: var(--accent-color); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">
                            <i class="fas fa-redo"></i> 重新安装
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
