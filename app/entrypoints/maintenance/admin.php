<?php
// VenlanChat Admin Panel v2.0 - Enhanced
session_start();
require_once __DIR__ . '/../../../db_connect.php';

$config_path = __DIR__ . '/../../../config.php';

// 加载配置文件，如果不存在则创建一个默认的
if (file_exists($config_path)) {
    $config = require $config_path;
} else {
    // 默认配置
    $defaultConfig = [
        'admin_password' => 'admin', // 默认密码，强烈建议首次登录后修改
        'db_file' => 'messages.json',
        'log_file' => 'access_log.txt',
    ];
    // 将默认配置写入文件
    file_put_contents($config_path, '<?php return ' . var_export($defaultConfig, true) . ';');
    $config = $defaultConfig;
}

// 检查管理员是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // 处理登录请求
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        if (password_verify($_POST['admin_password'], $config['admin_password'])) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            // 旧版密码兼容 (如果密码不是哈希)
            if ($_POST['admin_password'] === $config['admin_password']) {
                 $_SESSION['admin_logged_in'] = true;
                 // 将旧密码更新为哈希值
                 $config['admin_password'] = password_hash($config['admin_password'], PASSWORD_DEFAULT);
                 file_put_contents($config_path, '<?php return ' . var_export($config, true) . ';');
                 header('Location: ' . $_SERVER['PHP_SELF']);
                 exit;
            }
            $loginError = '密码错误';
        }
    }
    
    // 显示登录页面
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>VenlanChat - 管理员登录</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            :root {
                --bg-color: #1a1a1a; --primary-color: #3498db; --text-primary: #f0f0f0; --text-secondary: #b0b0b0;
                --card-bg: #2c2c2c; --border-color: #333333; --input-bg: #3a3a3a; --danger-color: #e74c3c;
                --shadow-color: rgba(0, 0, 0, 0.5); --border-radius: 8px;
            }
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg-color); color: var(--text-primary);
                min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
            }
            .login-container {
                background: var(--card-bg); padding: 40px; border-radius: var(--border-radius);
                box-shadow: 0 10px 30px var(--shadow-color); width: 100%; max-width: 420px;
                animation: fadeIn 0.8s ease-out;
            }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
            .login-header { text-align: center; margin-bottom: 30px; }
            .login-header h1 { color: var(--text-primary); margin-bottom: 10px; font-size: 2.2em; font-weight: 700; }
            .login-header p { color: var(--text-secondary); font-size: 15px; }
            .form-group { margin-bottom: 25px; position: relative; }
            .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary); font-size: 15px; }
            .form-group input {
                width: 100%; padding: 14px 14px 14px 45px; border: 1px solid var(--border-color);
                border-radius: var(--border-radius); font-size: 16px; background: var(--input-bg);
                color: var(--text-primary); transition: all 0.3s ease;
            }
            .form-group input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 8px rgba(52, 152, 219, 0.2); }
            .form-group .icon { position: absolute; left: 15px; top: 50%; transform: translateY(10%); color: var(--text-secondary); }
            .login-btn {
                width: 100%; background: var(--primary-color); color: white; border: none; padding: 15px;
                border-radius: var(--border-radius); font-size: 16px; font-weight: bold; cursor: pointer;
                transition: all 0.3s ease; box-shadow: 0 4px 15px var(--shadow-color);
            }
            .login-btn:hover { background: #2980b9; transform: translateY(-2px); }
            .error { background-color: #3a2525; color: #ff9494; padding: 15px; border-radius: var(--border-radius); margin-bottom: 20px; border: 1px solid #5c2a2a; }
            .back-link { text-align: center; margin-top: 25px; }
            .back-link a { color: var(--primary-color); text-decoration: none; transition: color 0.3s ease; }
            .back-link a:hover { color: #2980b9; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <h1><i class="fas fa-lock"></i> 管理员登录</h1>
                <p>VenlanChat 管理面板</p>
            </div>
            <?php if (isset($loginError)): ?>
                <div class="error"><?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="admin_password">管理员密码</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                    <i class="fas fa-key icon"></i>
                </div>
                <button type="submit" name="login" class="login-btn">登录</button>
            </form>
            <div class="back-link">
                <a href="index.php"><i class="fas fa-arrow-left"></i> 返回聊天室</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// -----------------------------------------------------------------------------
// -- POST请求处理中心 --
// 所有表单提交的动作都在这里处理，执行于任何HTML输出之前
// -----------------------------------------------------------------------------
$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    switch ($action) {
        case 'logout':
            session_destroy();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;

        case 'clear_messages':
            file_put_contents($config['db_file'], json_encode([]));
            $_SESSION['success_message'] = '所有公共消息已清空';
            break;

        case 'clear_logs':
            file_put_contents($config['log_file'], '');
            $_SESSION['success_message'] = '访问日志已清空';
            break;

        case 'delete_user':
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                $mysqli = get_db_connection();
                $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = '用户删除成功';
                } else {
                    $_SESSION['error_message'] = '删除用户失败: ' . $stmt->error;
                }
                $stmt->close();
                $mysqli->close();
            }
            break;
            
        case 'delete_message':
            $msg_index = intval($_POST['msg_index'] ?? -1);
            if ($msg_index >= 0) {
                $messages = json_decode(file_get_contents($config['db_file']), true) ?: [];
                if (isset($messages[$msg_index])) {
                    unset($messages[$msg_index]);
                    // 重建索引
                    $messages = array_values($messages);
                    file_put_contents($config['db_file'], json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $_SESSION['success_message'] = '消息删除成功';
                } else {
                    $_SESSION['error_message'] = '消息不存在或已被删除';
                }
            }
            break;
            
        case 'save_settings':
            $newConfig = $config;
            $newConfig['db_file'] = htmlspecialchars($_POST['db_file']);
            $newConfig['log_file'] = htmlspecialchars($_POST['log_file']);
            
            if (!empty($_POST['admin_password'])) {
                $newConfig['admin_password'] = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
                $_SESSION['success_message'] = '设置已保存，新密码已生效。';
            } else {
                $_SESSION['success_message'] = '设置已保存。';
            }
            
            // 将更新后的配置写回文件
            if (is_writable($config_path)) {
                file_put_contents($config_path, '<?php return ' . var_export($newConfig, true) . ';');
            } else {
                $_SESSION['error_message'] = '错误: config.php 文件不可写。请检查文件权限。';
            }
            // 重新加载配置
            $config = $newConfig;
            break;
    }
    
    // 处理完POST请求后，重定向以防止表单重复提交
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// 获取会话消息（成功或失败提示）
$successMessage = $_SESSION['success_message'] ?? null;
$errorMessage = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);


// -----------------------------------------------------------------------------
// -- 辅助函数 --
// -----------------------------------------------------------------------------

// 获取统计信息
function getStats($config) {
    $stats = [
        'total_messages' => 0, 'total_access' => 0, 'total_users' => 0,
        'private_messages' => 0, 'data_size' => 0
    ];
    
    if (file_exists($config['db_file'])) {
        $messages = json_decode(file_get_contents($config['db_file']), true) ?: [];
        $stats['total_messages'] = count($messages);
        $stats['data_size'] += filesize($config['db_file']);
    }
    
    if (file_exists($config['log_file'])) {
        $logLines = file($config['log_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stats['total_access'] = count($logLines);
        $stats['data_size'] += filesize($config['log_file']);
    }
    
    $mysqli = get_db_connection();
    if ($mysqli) {
        $result = $mysqli->query("SELECT COUNT(*) as total FROM users");
        if($result) $stats['total_users'] = $result->fetch_assoc()['total'];
        
        $result = $mysqli->query("SELECT COUNT(*) as total FROM private_messages");
        if($result) $stats['private_messages'] = $result->fetch_assoc()['total'];
        
        $mysqli->close();
    }
    return $stats;
}

// 获取当前页面标识
$currentPage = $_GET['page'] ?? 'dashboard';

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VenlanChat - 管理面板</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #121212; --primary-color: #3498db; --success-color: #2ecc71; --danger-color: #e74c3c;
            --text-primary: #e0e0e0; --text-secondary: #a0a0a0; --border-color: #333; --card-bg: #1e1e1e;
            --header-bg: #1e1e1e; --sidebar-bg: #1e1e1e; --hover-bg: #2a2a2a; --shadow-color: rgba(0, 0, 0, 0.5);
            --border-radius: 8px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: var(--bg-color); color: var(--text-primary); display: flex; flex-direction: column; min-height: 100vh; font-size: 15px; }
        a { text-decoration: none; color: var(--primary-color); }
        .header { background: var(--header-bg); color: white; padding: 0 20px; height: 60px; display: flex; align-items: center; box-shadow: 0 2px 10px var(--shadow-color); position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid var(--border-color); }
        .header h1 { font-size: 1.4em; display: flex; align-items: center; gap: 10px; margin-right: auto; }
        .main-container { display: flex; flex: 1; }
        .sidebar { background: var(--sidebar-bg); width: 240px; min-width: 240px; height: calc(100vh - 60px); position: sticky; top: 60px; overflow-y: auto; border-right: 1px solid var(--border-color); padding-top: 10px; transition: width 0.3s ease, min-width 0.3s ease; }
        .sidebar-nav { padding: 10px 0; }
        .nav-item { padding: 12px 20px; display: flex; align-items: center; gap: 15px; color: var(--text-secondary); transition: all 0.2s ease; border-left: 3px solid transparent; }
        .nav-item:hover { background: var(--hover-bg); color: var(--text-primary); }
        .nav-item.active { background: var(--hover-bg); color: var(--primary-color); border-left-color: var(--primary-color); font-weight: 600; }
        .nav-item i { width: 20px; text-align: center; font-size: 1.1em; }
        .main-content { flex: 1; padding: 30px; }
        .page-header { margin-bottom: 30px; }
        .page-header h2 { font-size: 2em; display: flex; align-items: center; gap: 12px; }
        .section { background: var(--card-bg); border-radius: var(--border-radius); box-shadow: 0 4px 12px var(--shadow-color); margin-bottom: 30px; border: 1px solid var(--border-color); }
        .section-header { padding: 15px 20px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px; }
        .section-header h3 { font-size: 1.2em; margin: 0; }
        .section-body { padding: 20px; }
        .btn { display: inline-block; padding: 9px 18px; border: none; border-radius: var(--border-radius); text-decoration: none; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 14px; }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-danger { background: var(--danger-color); color: white; }
        .btn-success { background: var(--success-color); color: white; }
        .btn:hover { opacity: 0.85; transform: translateY(-1px); }
        .message-box { padding: 15px; border-radius: var(--border-radius); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; animation: slideDown 0.5s ease-out; }
        .success-message { background: rgba(46, 204, 113, 0.2); color: #a3f7bf; border: 1px solid var(--success-color); }
        .error-message { background: rgba(231, 76, 60, 0.2); color: #ff9494; border: 1px solid var(--danger-color); }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        /* 表格样式 */
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
        .data-table th { background: #2a2a2a; font-weight: 600; }
        .data-table tbody tr:hover { background-color: var(--hover-bg); }
        .data-table td .btn { padding: 5px 10px; font-size: 13px; }
        /* 模态框样式 */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7); justify-content: center; align-items: center; }
        .modal-content { background: var(--card-bg); margin: auto; padding: 25px; border: 1px solid var(--border-color); width: 90%; max-width: 450px; border-radius: var(--border-radius); box-shadow: 0 5px 15px var(--shadow-color); animation: fadeIn 0.3s; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h2 { margin: 0; font-size: 1.5em; }
        .modal-body { margin-bottom: 25px; color: var(--text-secondary); line-height: 1.6; }
        .modal-footer { text-align: right; }
        .modal-footer .btn { margin-left: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius); background: #3a3a3a; color: var(--text-primary); font-size: 1em; }
        .form-group input:focus { outline: none; border-color: var(--primary-color); }
        .search-box { margin-bottom: 20px; width: 100%; max-width: 300px; padding: 10px; }
        
        @media (max-width: 992px) {
            .sidebar { width: 70px; min-width: 70px; }
            .sidebar .nav-item span { display: none; }
            .sidebar .nav-item { justify-content: center; }
        }
        @media (max-width: 768px) {
            .main-container { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: static; border-right: none; border-bottom: 1px solid var(--border-color); }
            .sidebar-nav { display: flex; justify-content: space-around; overflow-x: auto; padding: 0; }
            .nav-item { border-left: none; border-bottom: 3px solid transparent; flex: 1; justify-content: center; }
            .nav-item.active { border-left: none; border-bottom-color: var(--primary-color); }
            .main-content { padding: 20px 15px; }
            .page-header h2 { font-size: 1.6em; }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1><i class="fas fa-shield-halved"></i> VenlanChat</h1>
        <form method="POST">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> 退出</button>
        </form>
    </header>

    <div class="main-container">
        <aside class="sidebar">
            <nav class="sidebar-nav">
                <a href="?page=dashboard" class="nav-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i><span>仪表盘</span>
                </a>
                <a href="?page=users" class="nav-item <?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i><span>用户管理</span>
                </a>
                <a href="?page=messages" class="nav-item <?php echo $currentPage === 'messages' ? 'active' : ''; ?>">
                    <i class="fas fa-comments"></i><span>消息管理</span>
                </a>
                <a href="?page=logs" class="nav-item <?php echo $currentPage === 'logs' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i><span>系统日志</span>
                </a>
                <a href="?page=backup" class="nav-item <?php echo $currentPage === 'backup' ? 'active' : ''; ?>">
                    <i class="fas fa-download"></i><span>数据备份</span>
                </a>
                <a href="?page=settings" class="nav-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i><span>系统设置</span>
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <?php if ($successMessage): ?>
                <div class="message-box success-message"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <div class="message-box error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <?php // 页面内容切换
            switch ($currentPage):
            
            // ------------------ 用户管理页面 ------------------
            case 'users': ?>
                <div class="page-header"><h2><i class="fas fa-users"></i> 用户管理</h2></div>
                <div class="section">
                    <div class="section-header">
                        <h3><i class="fas fa-list"></i> 注册用户列表</h3>
                    </div>
                    <div class="section-body">
                         <input type="text" id="userSearch" onkeyup="searchTable('userSearch', 'userTable')" placeholder="搜索用户名或邮箱..." class="form-group input search-box">
                        <div class="table-container">
                            <table class="data-table" id="userTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>用户名</th>
                                        <th>邮箱</th>
                                        <th>注册时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $mysqli = get_db_connection();
                                    $result = $mysqli->query("SELECT id, username, email, created_at FROM users ORDER BY id DESC");
                                    while ($user = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <form method="POST" class="delete-form" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="button" class="btn btn-danger delete-btn" data-username="<?php echo htmlspecialchars($user['username']); ?>"><i class="fas fa-trash"></i> 删除</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; $mysqli->close(); ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php break;

            // ------------------ 消息管理页面 ------------------
            case 'messages': 
                $messages = file_exists($config['db_file']) ? json_decode(file_get_contents($config['db_file']), true) : [];
                $messages = array_reverse($messages); // 新消息在前
                ?>
                <div class="page-header"><h2><i class="fas fa-comments"></i> 消息管理</h2></div>
                <div class="section">
                    <div class="section-header">
                        <h3><i class="fas fa-list"></i> 公共聊天记录</h3>
                        <form method="POST" class="delete-form" style="margin-left: auto;">
                            <input type="hidden" name="action" value="clear_messages">
                            <button type="button" class="btn btn-danger delete-btn" data-username="所有消息"><i class="fas fa-trash"></i> 清空所有消息</button>
                        </form>
                    </div>
                    <div class="section-body">
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>用户</th>
                                        <th>消息内容</th>
                                        <th>时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($messages as $index => $msg): 
                                        $original_index = count($messages) - 1 - $index; ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($msg['username']); ?></td>
                                            <td><?php echo htmlspecialchars(mb_substr($msg['message'], 0, 100)) . (mb_strlen($msg['message']) > 100 ? '...' : ''); ?></td>
                                            <td><?php echo date('Y-m-d H:i:s', $msg['timestamp']); ?></td>
                                            <td>
                                                <form method="POST" class="delete-form" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete_message">
                                                    <input type="hidden" name="msg_index" value="<?php echo $original_index; ?>">
                                                    <button type="button" class="btn btn-danger delete-btn" data-username="这条消息"><i class="fas fa-trash"></i> 删除</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($messages)) echo '<tr><td colspan="4" style="text-align:center;">暂无消息</td></tr>'; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php break;
                
            // ------------------ 系统日志页面 ------------------
            case 'logs':
                $logs = file_exists($config['log_file']) ? file($config['log_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
                $logs = array_reverse($logs);
                ?>
                <div class="page-header"><h2><i class="fas fa-file-alt"></i> 系统日志</h2></div>
                <div class="section">
                    <div class="section-header">
                        <h3><i class="fas fa-history"></i> 访问记录</h3>
                        <form method="POST" class="delete-form" style="margin-left: auto;">
                            <input type="hidden" name="action" value="clear_logs">
                            <button type="button" class="btn btn-danger delete-btn" data-username="所有访问日志"><i class="fas fa-trash"></i> 清空日志</button>
                        </form>
                    </div>
                    <div class="section-body">
                        <div class="table-container" style="max-height: 500px; overflow-y: auto;">
                            <table class="data-table">
                                <thead><tr><th>时间</th><th>IP地址</th><th>用户代理 (User Agent)</th></tr></thead>
                                <tbody>
                                    <?php foreach ($logs as $log): 
                                        if (preg_match('/\[(.*?)\] IP: (.*?) \| User Agent: (.*)/', $log, $matches)) {
                                            echo '<tr><td>' . htmlspecialchars($matches[1]) . '</td><td>' . htmlspecialchars($matches[2]) . '</td><td>' . htmlspecialchars($matches[3]) . '</td></tr>';
                                        }
                                    endforeach; ?>
                                    <?php if(empty($logs)) echo '<tr><td colspan="3" style="text-align:center;">暂无日志</td></tr>'; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php break;
                
            // ------------------ 数据备份页面 ------------------
            case 'backup': ?>
                <div class="page-header"><h2><i class="fas fa-download"></i> 数据备份</h2></div>
                <div class="section">
                    <div class="section-header"><h3><i class="fas fa-database"></i> 下载数据文件</h3></div>
                    <div class="section-body">
                        <p style="color: var(--text-secondary); margin-bottom: 25px; line-height: 1.7;">
                            在这里，您可以下载聊天室的核心数据文件作为备份。建议定期备份以防数据丢失。
                        </p>
                        <a href="<?php echo htmlspecialchars($config['db_file']); ?>" download="messages_backup.json" class="btn btn-success"><i class="fas fa-comments"></i> 下载消息备份</a>
                        <a href="<?php echo htmlspecialchars($config['log_file']); ?>" download="access_log_backup.txt" class="btn btn-success" style="margin-left: 15px;"><i class="fas fa-file-alt"></i> 下载日志备份</a>
                    </div>
                </div>
                <?php break;
                
            // ------------------ 系统设置页面 ------------------
            case 'settings': ?>
                 <div class="page-header"><h2><i class="fas fa-cog"></i> 系统设置</h2></div>
                 <div class="section">
                     <div class="section-header"><h3><i class="fas fa-edit"></i> 修改配置</h3></div>
                     <div class="section-body">
                        <?php if (!is_writable($config_path)): ?>
                        <div class="message-box error-message">
                            <i class="fas fa-exclamation-triangle"></i> <strong>警告:</strong> 配置文件 <code>config.php</code> 不可写。您将无法保存任何更改。请检查文件权限 (例如: `chmod 666 config.php`)。
                        </div>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="save_settings">
                            <div class="form-group">
                                <label for="admin_password">新管理员密码 (留空则不修改)</label>
                                <input type="password" id="admin_password" name="admin_password" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="db_file">消息文件名</label>
                                <input type="text" id="db_file" name="db_file" value="<?php echo htmlspecialchars($config['db_file']); ?>" class="form-control">
                            </div>
                             <div class="form-group">
                                <label for="log_file">日志文件名</label>
                                <input type="text" id="log_file" name="log_file" value="<?php echo htmlspecialchars($config['log_file']); ?>" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary" <?php if (!is_writable($config_path)) echo 'disabled'; ?>>
                                <i class="fas fa-save"></i> 保存设置
                            </button>
                        </form>
                     </div>
                 </div>
                <?php break;

            // ------------------ 仪表盘页面 (默认) ------------------
            default:
                $stats = getStats($config);
                ?>
                <div class="page-header"><h2><i class="fas fa-tachometer-alt"></i> 仪表盘</h2></div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['total_messages']; ?></div><div class="label">公共消息</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['total_users']; ?></div><div class="label">注册用户</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['total_access']; ?></div><div class="label">总访问次数</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['private_messages']; ?></div><div class="label">私聊消息</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo round($stats['data_size'] / 1024, 2); ?> KB</div><div class="label">数据大小</div>
                    </div>
                </div>
                 <style>.stat-card{background:var(--card-bg); padding:20px; border-radius:var(--border-radius); text-align:center; border:1px solid var(--border-color);}.number{font-size:2em; font-weight:bold;}.label{color:var(--text-secondary);}</style>

                <div class="section">
                    <div class="section-header"><h3><i class="fas fa-info-circle"></i> 系统信息</h3></div>
                    <div class="section-body" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                        <p><strong>PHP 版本:</strong> <?php echo phpversion(); ?></p>
                        <p><strong>服务器时间:</strong> <span id="server-time"><?php echo date('Y-m-d H:i:s'); ?></span></p>
                        <p><strong>系统版本:</strong> VenlanChat v2.0</p>
                        <p><strong>运行状态:</strong> <span style="color: var(--success-color);"><i class="fas fa-check-circle"></i> 正常</span></p>
                    </div>
                </div>
            <?php endswitch; ?>
        </main>
    </div>

    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-exclamation-triangle" style="color: var(--danger-color);"></i> 确认操作</h2>
            </div>
            <div class="modal-body">
                <p id="modalText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" id="cancelBtn" class="btn">取消</button>
                <button type="button" id="confirmBtn" class="btn btn-danger">确认删除</button>
            </div>
        </div>
    </div>

    <script>
        // 实时更新服务器时间
        setInterval(() => {
            const now = new Date();
            const timeStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0') + ' ' +
                String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0') + ':' + String(now.getSeconds()).padStart(2, '0');
            document.getElementById('server-time').textContent = timeStr;
        }, 1000);

        // 确认删除模态框逻辑
        const modal = document.getElementById('confirmModal');
        const modalText = document.getElementById('modalText');
        const confirmBtn = document.getElementById('confirmBtn');
        const cancelBtn = document.getElementById('cancelBtn');

        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('.delete-form');
                const username = this.getAttribute('data-username');
                modalText.innerHTML = `您确定要删除 "<strong>${username}</strong>" 吗？此操作不可恢复！`;
                modal.style.display = 'flex';

                confirmBtn.onclick = function() {
                    form.submit();
                }
            });
        });
        
        cancelBtn.onclick = () => modal.style.display = 'none';
        window.onclick = (event) => {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // 表格实时搜索功能
        function searchTable(inputId, tableId) {
            const input = document.getElementById(inputId);
            const filter = input.value.toUpperCase();
            const table = document.getElementById(tableId);
            const tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) { // 从1开始，跳过表头
                let td = tr[i].getElementsByTagName("td");
                let textValue = "";
                for (let j = 0; j < td.length -1; j++) { // 最后一个td是操作按钮，不搜索
                     if (td[j]) {
                        textValue += td[j].textContent || td[j].innerText;
                     }
                }
                if (textValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    </script>
</body>
</html>
