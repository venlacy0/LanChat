<?php
// Access logging

function logAccess(string $message): void
{
    global $config;
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $logEntry = "[$timestamp] IP: $ip | $message | User-Agent: $userAgent\n";
    file_put_contents($config['log_file'], $logEntry, FILE_APPEND | LOCK_EX);
}
