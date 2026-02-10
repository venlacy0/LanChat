<?php
$chatConfig = [
    'userId' => $_SESSION['user_id'] ?? 0,
    'csrfToken' => $_SESSION['csrf_token'] ?? '',
    'initialSettings' => $initialSettings ?? []
];
$jsFiles = [
    __DIR__ . '/../../assets/js/chat/bootstrap.js',
    __DIR__ . '/../../assets/js/chat/utils.js',
    __DIR__ . '/../../assets/js/chat/ui.js',
    __DIR__ . '/../../assets/js/chat/attachments.js',
    __DIR__ . '/../../assets/js/chat/messages.js',
    __DIR__ . '/../../assets/js/chat/settings.js',
    __DIR__ . '/../../assets/js/chat/init.js',
];
?>
<script>
window.VENCHAT_CONFIG = <?php echo json_encode($chatConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<?php foreach ($jsFiles as $jsPath): ?>
<?php if (is_readable($jsPath)): ?>
<script>
<?php readfile($jsPath); ?>
</script>
<?php endif; ?>
<?php endforeach; ?>
