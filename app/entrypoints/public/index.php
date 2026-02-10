<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../helpers/security.php';
require_once __DIR__ . '/../../helpers/logger.php';
require_once __DIR__ . '/../../helpers/ip.php';
require_once __DIR__ . '/../../helpers/rate_limiter.php';
require_once __DIR__ . '/../../services/messages.php';
require_once __DIR__ . '/../../services/user.php';
require_once __DIR__ . '/../../services/settings.php';

// Delegate POST requests to API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/api.php';
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$mysqli = get_db_connection();
$current_user = getCurrentUser($mysqli, $_SESSION['user_id']);
if (!$current_user) {
    session_destroy();
    header('Location: login.php');
    exit;
}
$current_user['avatar'] = ensureAvatar($current_user['avatar'] ?? null);
$_SESSION['username'] = $current_user['username'];
$mysqli->close();

cleanOldRateFiles();
logAccess("Mobile page accessed by {$_SESSION['username']}");
$initialSettings = getUserSettings($_SESSION['user_id']);
?>
<?php
require __DIR__ . '/../../views/chat.php';

?>
