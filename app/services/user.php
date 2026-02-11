<?php
// User related helpers

function getCurrentUser(mysqli $mysqli, int $userId): ?array
{
    $stmt = $mysqli->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc() ?: null;
    $stmt->close();
    return $user;
}

function ensureAvatar(?string $avatar): string
{
    // 默认头像：使用项目内静态资源，避免 data URL 过长导致写入数据库失败（avatar 列通常是 VARCHAR(255)）。
    $default = 'assets/images/default_avatar.svg';

    if (!empty($avatar)) {
        $normalized = trim((string)$avatar);
        if (
            $normalized !== '' &&
            stripos($normalized, 'default_avatar.png') === false &&
            stripos($normalized, 'default_avatar.svg') === false &&
            stripos($normalized, 'data:image/') !== 0
        ) {
            return $normalized;
        }
    }

    return $default;
}

function updateLastSeen(int $user_id): bool
{
    $user_id = intval($user_id);
    if ($user_id <= 0) {
        return false;
    }

    $mysqli = get_db_connection();
    $stmt = $mysqli->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    if (!$stmt) {
        $mysqli->close();
        return false;
    }

    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    $stmt->close();
    $mysqli->close();

    return $result;
}

function getUserList(): array
{
    $mysqli = get_db_connection();
    $stmt = $mysqli->prepare("SELECT id, username, avatar, last_seen FROM users WHERE id != ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $row['avatar'] = ensureAvatar($row['avatar'] ?? null);
        $users[] = $row;
    }
    $stmt->close();
    $mysqli->close();
    return $users;
}
