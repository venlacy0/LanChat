<?php
$settings = getUserSettings($_SESSION['user_id']);
echo json_encode(['success' => true, 'settings' => $settings]);
