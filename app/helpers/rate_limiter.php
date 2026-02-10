<?php
// Per-user rate limiter stored in data/rate_*.json

function checkRateLimit(int $user_id): bool
{
    global $config;
    $rateFile = __DIR__ . "/../../data/rate_$user_id.json";
    $now = time();

    if (file_exists($rateFile)) {
        $rateData = json_decode(file_get_contents($rateFile), true);
        $rateData = array_filter($rateData ?? [], function ($timestamp) use ($now) {
            return ($now - $timestamp) < 60;
        });

        if (count($rateData) >= $config['rate_limit']) {
            return false;
        }
    } else {
        $rateData = [];
    }

    $rateData[] = $now;
    file_put_contents($rateFile, json_encode($rateData), LOCK_EX);
    return true;
}

function cleanOldRateFiles(): void
{
    $files = glob(__DIR__ . '/../../data/rate_*.json');
    $now = time();
    foreach ($files as $file) {
        if ($now - filemtime($file) > 3600) {
            @unlink($file);
        }
    }
}
