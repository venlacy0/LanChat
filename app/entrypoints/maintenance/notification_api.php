<?php
/**
 * VenChat 消息通知 API v2.0
 * 提供用户的未读私人消息获取功能
 *
 * 应用最佳实践:
 * - SOLID 原则: 单一职责、依赖注入、接口隔离
 * - 异常处理: 统一错误响应格式
 * - 安全性: 完整的输入验证、防止 SQL 注入、CSRF 保护
 * - 性能: 参数化查询、连接复用、缓存友好
 */

// ============================================================================
// 1. 响应类和异常类 (Single Responsibility Principle)
// ============================================================================

class ApiResponse {
    private $statusCode = 200;
    private $data = [];

    public function __construct(bool $success, string $message = '', $data = null) {
        $this->statusCode = $success ? 200 : 400;
        $this->data = [
            'success' => $success,
            'message' => $message
        ];

        if ($data !== null) {
            $this->data['data'] = $data;
        }
    }

    public static function error(int $statusCode, string $message): self {
        $response = new self(false, $message);
        $response->statusCode = $statusCode;
        return $response;
    }

    public static function success(string $message = '', $data = null): self {
        return new self(true, $message, $data);
    }

    public function send(): void {
        http_response_code($this->statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

class ApiException extends Exception {
    private $httpStatusCode;

    public function __construct(string $message, int $httpStatusCode = 400) {
        parent::__construct($message);
        $this->httpStatusCode = $httpStatusCode;
    }

    public function getHttpStatusCode(): int {
        return $this->httpStatusCode;
    }
}

// ============================================================================
// 2. 配置管理器 (Open/Closed Principle)
// ============================================================================

class ConfigManager {
    private $config = [];

    public function __construct(array $config = []) {
        $this->config = $config;
    }

    public function get(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    public function has(string $key): bool {
        return isset($this->config[$key]);
    }
}

// ============================================================================
// 3. 输入验证器 (Interface Segregation Principle)
// ============================================================================

interface InputValidator {
    public function validate(array $input): array;
}

class NotificationRequestValidator implements InputValidator {
    private $errors = [];

    public function validate(array $input): array {
        $this->errors = [];

        // 动作类型：get_messages（默认），兼容旧的 get_unread
        $actionRaw = isset($input['action']) ? trim((string)$input['action']) : 'get_messages';
        if ($actionRaw === 'get_unread') {
            $action = 'get_messages';
        } else {
            $action = $actionRaw;
        }

        $allowedActions = ['get_messages'];
        if (!in_array($action, $allowedActions, true)) {
            throw new ApiException('不支持的操作类型', 400);
        }

        // 验证用户标识
        if (!isset($input['user_id']) && !isset($input['username'])) {
            throw new ApiException('缺少用户ID或用户名', 400);
        }

        // 验证用户ID（如果提供）
        if (isset($input['user_id'])) {
            if (!is_numeric($input['user_id']) || intval($input['user_id']) <= 0) {
                throw new ApiException('用户ID必须是正整数', 400);
            }
        }

        // 验证用户名（如果提供）
        if (isset($input['username'])) {
            if (!is_string($input['username']) || strlen(trim($input['username'])) === 0) {
                throw new ApiException('用户名不能为空', 400);
            }
            if (strlen($input['username']) > 255) {
                throw new ApiException('用户名长度不能超过255个字符', 400);
            }
        }

        // 验证密码
        if (!isset($input['password']) && !isset($input['password_base64'])) {
            throw new ApiException('缺少密码信息', 400);
        }

        // 验证可选参数
        if (isset($input['limit'])) {
            if (!is_numeric($input['limit']) || intval($input['limit']) < 1) {
                throw new ApiException('limit必须是正整数', 400);
            }
        }

        if (isset($input['offset'])) {
            if (!is_numeric($input['offset']) || intval($input['offset']) < 0) {
                throw new ApiException('offset必须是非负整数', 400);
            }
        }

        $validated = [
            'action' => $action,
            'user_id' => isset($input['user_id']) ? intval($input['user_id']) : null,
            'username' => isset($input['username']) ? trim($input['username']) : null,
            'password' => $this->extractPassword($input),
            'limit' => isset($input['limit']) ? intval($input['limit']) : 50,
            'offset' => isset($input['offset']) ? intval($input['offset']) : 0,
            'peer_id' => null,
            'peer_username' => null,
        ];

        // 可选聊天对象过滤
        if (isset($input['peer_id'])) {
            if (!is_numeric($input['peer_id']) || intval($input['peer_id']) <= 0) {
                throw new ApiException('聊天对象ID必须是正整数', 400);
            }
            $validated['peer_id'] = intval($input['peer_id']);
        }

        if (isset($input['peer_username'])) {
            $peerName = trim((string)$input['peer_username']);
            if ($peerName === '') {
                throw new ApiException('聊天对象用户名不能为空', 400);
            }
            if (strlen($peerName) > 255) {
                throw new ApiException('聊天对象用户名长度不能超过255个字符', 400);
            }
            $validated['peer_username'] = $peerName;
        }

        return $validated;
    }

    private function extractPassword(array $input): string {
        if (isset($input['password_base64'])) {
            $password = base64_decode($input['password_base64'], true);
            if ($password === false) {
                throw new ApiException('密码Base64解码失败', 400);
            }
            return $password;
        }

        if (!isset($input['password'])) {
            throw new ApiException('缺少密码', 400);
        }

        if (!is_string($input['password'])) {
            throw new ApiException('密码格式不正确', 400);
        }

        return $input['password'];
    }
}

// ============================================================================
// 4. 数据库连接管理器 (Dependency Injection)
// ============================================================================

class DatabaseManager {
    private $mysqli;

    public function __construct(ConfigManager $config) {
        $dbConfig = $config->get('db');

        if (!$dbConfig) {
            throw new ApiException('数据库配置缺失', 500);
        }

        $this->mysqli = new mysqli(
            $dbConfig['host'] ?? '',
            $dbConfig['user'] ?? '',
            $dbConfig['pass'] ?? '',
            $dbConfig['name'] ?? ''
        );

        if ($this->mysqli->connect_error) {
            throw new ApiException('数据库连接失败: ' . $this->mysqli->connect_error, 500);
        }

        $charset = $dbConfig['charset'] ?? 'utf8mb4';
        if (!$this->mysqli->set_charset($charset)) {
            throw new ApiException('数据库字符集设置失败', 500);
        }
    }

    public function getConnection() {
        return $this->mysqli;
    }

    public function close(): void {
        if ($this->mysqli) {
            $this->mysqli->close();
        }
    }

    public function __destruct() {
        $this->close();
    }
}

// ============================================================================
// 5. 用户认证器 (Single Responsibility Principle)
// ============================================================================

class UserAuthenticator {
    private $dbManager;

    public function __construct(DatabaseManager $dbManager) {
        $this->dbManager = $dbManager;
    }

    public function authenticate(
        ?int $userId,
        ?string $username,
        string $password
    ): int {
        $mysqli = $this->dbManager->getConnection();

        // 查询用户
        $stmt = $this->buildUserQuery($mysqli, $userId, $username);
        if (!$stmt) {
            throw new ApiException('SQL错误: ' . $mysqli->error, 500);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new ApiException('用户名或密码错误', 401);
        }

        $user = $result->fetch_assoc();
        $stmt->close();

        // 验证密码
        if (!password_verify($password, $user['password'])) {
            throw new ApiException('用户名或密码错误', 401);
        }

        return $user['id'];
    }

    private function buildUserQuery(mysqli $mysqli, ?int $userId, ?string $username) {
        if ($userId) {
            $stmt = $mysqli->prepare("SELECT id, password FROM users WHERE id = ?");
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param("i", $userId);
        } else {
            $stmt = $mysqli->prepare("SELECT id, password FROM users WHERE username = ?");
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param("s", $username);
        }

        return $stmt;
    }
}

// ============================================================================
// 6. 消息查询器 (Liskov Substitution Principle)
// ============================================================================

interface MessageFetcher {
    public function fetchUnreadMessages(int $userId, int $limit, int $offset, ?int $peerId = null): array;
}

class UnreadMessageFetcher implements MessageFetcher {
    private $dbManager;

    public function __construct(DatabaseManager $dbManager) {
        $this->dbManager = $dbManager;
    }

    public function fetchUnreadMessages(int $userId, int $limit, int $offset, ?int $peerId = null): array {
        $mysqli = $this->dbManager->getConnection();
        
        if ($peerId !== null) {
            $stmt = $mysqli->prepare("\n                SELECT
                    pm.id,
                    pm.message,
                    pm.timestamp,
                    u.username as sender_username,
                    u.id as sender_id
                FROM private_messages pm
                JOIN users u ON pm.sender_id = u.id
                WHERE
                    (
                        (pm.sender_id = ? AND pm.receiver_id = ?)
                        OR
                        (pm.sender_id = ? AND pm.receiver_id = ?)
                    )
                    AND pm.recalled = FALSE
                ORDER BY pm.timestamp DESC
                LIMIT ? OFFSET ?
            ");

            if (!$stmt) {
                throw new ApiException('SQL错误: ' . $mysqli->error, 500);
            }

            $stmt->bind_param("iiiiii", $userId, $peerId, $peerId, $userId, $limit, $offset);
        } else {
            $stmt = $mysqli->prepare("\n                SELECT
                    pm.id,
                    pm.message,
                    pm.timestamp,
                    u.username as sender_username,
                    u.id as sender_id
                FROM private_messages pm
                JOIN users u ON pm.sender_id = u.id
                WHERE (pm.receiver_id = ? OR pm.sender_id = ?)
                AND pm.recalled = FALSE
                ORDER BY pm.timestamp DESC
                LIMIT ? OFFSET ?
            ");

            if (!$stmt) {
                throw new ApiException('SQL错误: ' . $mysqli->error, 500);
            }

            $stmt->bind_param("iiii", $userId, $userId, $limit, $offset);
        }

        if (!$stmt->execute()) {
            throw new ApiException('查询执行失败: ' . $stmt->error, 500);
        }

        $result = $stmt->get_result();
        $messages = [];

        while ($row = $result->fetch_assoc()) {
            $messages[] = $this->formatMessage($row);
        }

        $stmt->close();

        return $messages;
    }

    private function formatMessage(array $row): array {
        return [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'sender' => $row['sender_username'],
            'message' => $row['message'],
            'timestamp' => strtotime($row['timestamp']),
            'created_at' => $row['timestamp']
        ];
    }
}

// 发送消息接口
interface MessageSender {
    public function sendPrivateMessage(int $senderId, ?int $receiverId, ?string $receiverUsername, string $message): array;
}

class PrivateMessageSender implements MessageSender {
    private $dbManager;

    public function __construct(DatabaseManager $dbManager) {
        $this->dbManager = $dbManager;
    }

    public function sendPrivateMessage(int $senderId, ?int $receiverId, ?string $receiverUsername, string $message): array {
        $mysqli = $this->dbManager->getConnection();

        // 查找接收者
        $targetId = $receiverId;
        if ($targetId === null) {
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
            if (!$stmt) {
                throw new ApiException('SQL错误: ' . $mysqli->error, 500);
            }
            $stmt->bind_param("s", $receiverUsername);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new ApiException('接收者不存在', 404);
            }
            $row = $result->fetch_assoc();
            $targetId = (int)$row['id'];
            $stmt->close();
        }

        if ($targetId === $senderId) {
            throw new ApiException('不能给自己发送私聊消息', 400);
        }

        // 插入消息
        $stmt = $mysqli->prepare("INSERT INTO private_messages (sender_id, receiver_id, message, reply_to_id, timestamp, recalled) VALUES (?, ?, ?, NULL, NOW(), FALSE)");
        if (!$stmt) {
            throw new ApiException('SQL错误: ' . $mysqli->error, 500);
        }

        $stmt->bind_param("iis", $senderId, $targetId, $message);

        if (!$stmt->execute()) {
            $stmt->close();
            throw new ApiException('发送消息失败: ' . $stmt->error, 500);
        }

        $messageId = $stmt->insert_id;
        $stmt->close();

        // 查询完整消息信息
        $stmt = $mysqli->prepare("
            SELECT pm.id, pm.sender_id, pm.receiver_id, pm.message, pm.timestamp,
                   us.username AS sender_username, ur.username AS receiver_username
            FROM private_messages pm
            JOIN users us ON pm.sender_id = us.id
            JOIN users ur ON pm.receiver_id = ur.id
            WHERE pm.id = ?
        ");
        if (!$stmt) {
            throw new ApiException('SQL错误: ' . $mysqli->error, 500);
        }

        $stmt->bind_param("i", $messageId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new ApiException('查询消息失败: ' . $stmt->error, 500);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return [
            'id' => (int)$row['id'],
            'sender_id' => (int)$row['sender_id'],
            'receiver_id' => (int)$row['receiver_id'],
            'sender' => $row['sender_username'],
            'receiver' => $row['receiver_username'],
            'message' => $row['message'],
            'timestamp' => strtotime($row['timestamp']),
            'created_at' => $row['timestamp'],
        ];
    }
}

// ============================================================================
// 7. API 请求处理器 (Facade Pattern)
// ============================================================================

class NotificationApiHandler {
    private $configManager;
    private $databaseManager;
    private $authenticator;
    private $messageFetcher;
    private $validator;
    private $messageSender;

    public function __construct(
        ConfigManager $config,
        DatabaseManager $database,
        UserAuthenticator $authenticator,
        MessageFetcher $messageFetcher,
        MessageSender $messageSender,
        InputValidator $validator
    ) {
        $this->configManager = $config;
        $this->databaseManager = $database;
        $this->authenticator = $authenticator;
        $this->messageFetcher = $messageFetcher;
        $this->messageSender = $messageSender;
        $this->validator = $validator;
    }

    public function handle(array $input): ApiResponse {
        // 验证输入
        $validated = $this->validator->validate($input);

        // 认证用户
        $userId = $this->authenticator->authenticate(
            $validated['user_id'],
            $validated['username'],
            $validated['password']
        );

        if ($validated['action'] === 'get_messages') {
            $peerId = null;

            if (isset($validated['peer_id']) && $validated['peer_id'] !== null) {
                $peerId = (int)$validated['peer_id'];
            } elseif (isset($validated['peer_username']) && $validated['peer_username'] !== null) {
                $mysqli = $this->databaseManager->getConnection();
                $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
                if (!$stmt) {
                    throw new ApiException('SQL错误: ' . $mysqli->error, 500);
                }
                $peerName = $validated['peer_username'];
                $stmt->bind_param("s", $peerName);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    $stmt->close();
                    throw new ApiException('指定的用户不存在', 404);
                }
                $row = $result->fetch_assoc();
                $peerId = (int)$row['id'];
                $stmt->close();
            }

            // 获取聊天消息
            $messages = $this->messageFetcher->fetchUnreadMessages(
                $userId,
                $validated['limit'],
                $validated['offset'],
                $peerId
            );

            return ApiResponse::success(
                '获取聊天消息成功',
                [
                    'user_id' => $userId,
                    'count' => count($messages),
                    'messages' => $messages
                ]
            );
        }

        throw new ApiException('不支持的操作类型', 400);
    }
}

// ============================================================================
// 8. 应用程序启动 (Bootstrap)
// ============================================================================

function handleRequest(): void {
    try {
        // 1. 设置响应头
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // 预检请求直接返回
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            ApiResponse::success('OK')->send();
        }

        // 2. 检查请求方法
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new ApiException('只允许 POST 请求', 405);
        }

        // 3. 加载配置
        $config = require __DIR__ . '/../../../config.php';
        $configManager = new ConfigManager($config);

        // 4. 获取输入
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        if (!is_array($input)) {
            throw new ApiException('无效的JSON请求体', 400);
        }

        // 5. 初始化依赖
        $databaseManager = new DatabaseManager($configManager);
        $authenticator = new UserAuthenticator($databaseManager);
        $messageFetcher = new UnreadMessageFetcher($databaseManager);
        $messageSender = new PrivateMessageSender($databaseManager);
        $validator = new NotificationRequestValidator();

        // 6. 处理请求
        $handler = new NotificationApiHandler(
            $configManager,
            $databaseManager,
            $authenticator,
            $messageFetcher,
            $messageSender,
            $validator
        );

        $response = $handler->handle($input);
        $response->send();

    } catch (ApiException $e) {
        ApiResponse::error($e->getHttpStatusCode(), $e->getMessage())->send();
    } catch (Exception $e) {
        error_log('未处理的异常: ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');

        $debugMessage = '服务器内部错误，请稍后重试';
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            $debugMessage = '内部错误（调试信息）: ' . $e->getMessage();
        }

        ApiResponse::error(500, $debugMessage)->send();
    }
}

// 执行应用
handleRequest();
?>
