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
    if (!empty($avatar)) {
        return $avatar;
    }

    return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"%3E%3Ccircle cx="50" cy="50" r="50" fill="%23e2e8f0"/%3E%3Ccircle cx="50" cy="35" r="18" fill="%2394a3b8"/%3E%3Cpath d="M 20 85 Q 20 60 50 60 Q 80 60 80 85 Z" fill="%2394a3b8"/%3E%3C/svg%3E';
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
