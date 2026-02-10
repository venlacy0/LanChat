<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // 开发时启用，生产环境注释掉
session_start();
require_once __DIR__ . '/../../../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $errors = [];

    // 验证输入
    if (empty($username) || empty($password) || empty($email)) {
        $errors[] = '所有字段均为必填';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '无效的邮箱地址';
    }
    if (strlen($password) < 6) {
        $errors[] = '密码长度至少为6个字符';
    }
    if (strlen($username) > 20) {
        $errors[] = '用户名长度不能超过20个字符';
    }

    // 数据库操作
    if (empty($errors)) {
        try {
            $mysqli = get_db_connection();
            if ($mysqli->connect_error) {
                error_log("数据库连接失败: " . $mysqli->connect_error);
                $errors[] = '数据库连接失败，请检查配置';
            } else {
                // 检查用户名或邮箱是否已存在
                $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                if (!$stmt) {
                    error_log("SQL 准备失败: " . $mysqli->error);
                    $errors[] = '数据库查询准备失败';
                } else {
                    $stmt->bind_param("ss", $username, $email);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $errors[] = '用户名或邮箱已被注册';
                    }
                    $stmt->close();
                }

                // 插入新用户
                if (empty($errors)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $mysqli->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
                    if (!$stmt) {
                        error_log("SQL 插入准备失败: " . $mysqli->error);
                        $errors[] = '数据库插入准备失败';
                    } else {
                        $stmt->bind_param("sss", $username, $hashed_password, $email);
                        if ($stmt->execute()) {
                            $_SESSION['user_id'] = $stmt->insert_id;
                            $_SESSION['username'] = $username;
                            header('Location: index.php');
                            exit;
                        } else {
                            error_log("注册失败: " . $mysqli->error);
                            $errors[] = '注册失败: ' . $mysqli->error;
                        }
                        $stmt->close();
                    }
                }
                $mysqli->close();
            }
        } catch (Exception $e) {
            error_log("注册异常: " . $e->getMessage());
            $errors[] = '服务器错误，请稍后重试';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VenlanChat - 用户注册</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            /* 与聊天页统一的暖灰底 + 克制强调色，避免大面积纯白与渐变 */
            --bg-color: #f8f6f3;
            --panel-bg: #fffefb;
            --text-color: #2d2a26;
            --input-bg: #fffefb;
            --border-color: #e8e4de;
            --accent-color: #3b82f6;
            --danger-color: #e05252;
            --shadow-color: rgba(45, 42, 38, 0.08);
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: var(--panel-bg);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 20px var(--shadow-color);
            width: 100%;
            max-width: 420px;
            border: 1px solid var(--border-color);
            animation: authIn 0.45s cubic-bezier(0.25, 0.1, 0.25, 1) both;
        }

        @keyframes authIn {
            from { opacity: 0; transform: translate3d(0, 10px, 0); }
            to { opacity: 1; transform: translate3d(0, 0, 0); }
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-header h1 {
            color: var(--text-color);
            margin-bottom: 8px;
            font-size: 1.75rem;
            font-weight: 600;
        }

        .register-header p {
            color: #718096;
            font-size: 15px;
        }

        .form-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
            font-size: 15px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            font-size: 16px;
            background: var(--input-bg);
            color: var(--text-color);
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
        }
        
        .form-group .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            transition: color 0.3s;
            pointer-events: none;
        }

        .form-group input:focus + .icon {
            color: var(--accent-color);
        }

        .register-btn {
            width: 100%;
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 24px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .register-btn:hover {
            background: #2563eb;
        }

        .error {
            background: #fff5f5;
            color: var(--danger-color);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .error p {
            margin: 5px 0;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .back-link a {
            color: var(--accent-color);
            text-decoration: none;
            font-size: 14px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 500px) {
            .register-container {
                padding: 30px 20px;
            }
            .register-header h1 {
                font-size: 1.5rem;
            }
            .form-group input, .register-btn {
                font-size: 15px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .register-container { animation: none; }
            * { transition: none !important; }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1><i class="fas fa-user-plus"></i> 用户注册</h1>
            <p>创建您的 VenlanChat 账号</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="registerForm">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required>
                <i class="fas fa-user icon"></i>
            </div>
            <div class="form-group">
                <label for="email">邮箱</label>
                <input type="email" id="email" name="email" required>
                <i class="fas fa-envelope icon"></i>
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required>
                <i class="fas fa-lock icon"></i>
            </div>
            <button type="submit" class="register-btn">注册</button>
        </form>
        
        <div class="back-link">
            <a href="index.php">← 返回聊天室</a>
            <a href="login.php">已有账号？登录</a>
        </div>
    </div>
</body>
</html>
