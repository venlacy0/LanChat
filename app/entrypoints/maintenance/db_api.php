<?php
// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 加载配置文件
$config = include __DIR__ . '/../../../config.php';

// 数据库配置
$db_config = $config['db'];

// API密钥（从配置文件中获取）
$api_key = $config['admin_password'];

// 检查API密钥
function checkApiKey() {
    global $api_key;
    
    // 检查Authorization头
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => '缺少Authorization头']);
        exit();
    }
    
    $auth_header = $headers['Authorization'];
    if (strpos($auth_header, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Authorization格式错误']);
        exit();
    }
    
    $provided_key = substr($auth_header, 7); // 移除 'Bearer ' 前缀
    if ($provided_key !== $api_key) {
        http_response_code(401);
        echo json_encode(['error' => 'API密钥无效']);
        exit();
    }
}

// 连接数据库
function getDatabaseConnection() {
    global $db_config;
    
    try {
        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['name']};charset={$db_config['charset']}";
        $pdo = new PDO($dsn, $db_config['user'], $db_config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => '数据库连接失败: ' . $e->getMessage()]);
        exit();
    }
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '只允许POST请求']);
    exit();
}

// 验证API密钥
checkApiKey();

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON格式错误: ' . json_last_error_msg()]);
    exit();
}

// 获取数据库连接
$pdo = getDatabaseConnection();

// 处理不同的操作
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'show_tables':
            // 显示所有表
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success' => true, 'data' => $tables]);
            break;
            
        case 'show_structure':
            // 显示表结构
            $table = $input['table'] ?? '';
            if (empty($table)) {
                http_response_code(400);
                echo json_encode(['error' => '缺少表名参数']);
                exit();
            }
            
            $stmt = $pdo->prepare("DESCRIBE `$table`");
            $stmt->execute();
            $columns = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $columns, 'table' => $table]);
            break;
            
        case 'select':
            // 执行SELECT查询
            $query = $input['query'] ?? '';
            if (empty($query)) {
                http_response_code(400);
                echo json_encode(['error' => '缺少查询语句']);
                exit();
            }
            
            // 确保是SELECT语句
            if (stripos(trim($query), 'SELECT') !== 0) {
                http_response_code(400);
                echo json_encode(['error' => '只允许执行SELECT查询']);
                exit();
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $rows, 'query' => $query]);
            break;
            
        case 'insert':
            // 插入数据
            $table = $input['table'] ?? '';
            $data = $input['data'] ?? [];
            
            if (empty($table) || empty($data) || !is_array($data)) {
                http_response_code(400);
                echo json_encode(['error' => '缺少必要的参数（表名或数据）']);
                exit();
            }
            
            $columns = array_keys($data);
            $column_list = '`' . implode('`, `', $columns) . '`';
            $placeholders = ':' . implode(', :', $columns);
            
            $sql = "INSERT INTO `$table` ($column_list) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($data);
            
            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => '数据插入成功', 
                    'insert_id' => $pdo->lastInsertId(),
                    'affected_rows' => $stmt->rowCount()
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => '数据插入失败']);
            }
            break;
            
        case 'update':
            // 更新数据
            $table = $input['table'] ?? '';
            $data = $input['data'] ?? [];
            $where = $input['where'] ?? [];
            
            if (empty($table) || empty($data) || empty($where)) {
                http_response_code(400);
                echo json_encode(['error' => '缺少必要的参数']);
                exit();
            }
            
            // 构建SET部分
            $set_parts = [];
            foreach (array_keys($data) as $column) {
                $set_parts[] = "`$column` = :set_$column";
            }
            $set_clause = implode(', ', $set_parts);
            
            // 构建WHERE部分
            $where_parts = [];
            foreach (array_keys($where) as $column) {
                $where_parts[] = "`$column` = :where_$column";
            }
            $where_clause = implode(' AND ', $where_parts);
            
            $sql = "UPDATE `$table` SET $set_clause WHERE $where_clause";
            
            // 合并数据参数
            $params = [];
            foreach ($data as $key => $value) {
                $params['set_' . $key] = $value;
            }
            foreach ($where as $key => $value) {
                $params['where_' . $key] = $value;
            }
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => '数据更新成功', 
                    'affected_rows' => $stmt->rowCount()
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => '数据更新失败']);
            }
            break;
            
        case 'delete':
            // 删除数据
            $table = $input['table'] ?? '';
            $where = $input['where'] ?? [];
            
            if (empty($table) || empty($where)) {
                http_response_code(400);
                echo json_encode(['error' => '缺少必要的参数']);
                exit();
            }
            
            // 构建WHERE部分
            $where_parts = [];
            foreach (array_keys($where) as $column) {
                $where_parts[] = "`$column` = :where_$column";
            }
            $where_clause = implode(' AND ', $where_parts);
            
            $sql = "DELETE FROM `$table` WHERE $where_clause";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($where);
            
            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => '数据删除成功', 
                    'affected_rows' => $stmt->rowCount()
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => '数据删除失败']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => '不支持的操作: ' . $action]);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => '数据库操作失败: ' . $e->getMessage()]);
}
?>
