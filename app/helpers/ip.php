<?php
// IP helpers

/**
 * 获取客户端 IP。
 * 注意：反向代理环境下 HTTP_X_FORWARDED_FOR 可能是逗号分隔列表，甚至包含端口，
 * 直接入库会导致超过 VARCHAR(45) 而插入失败。
 */
function getUserIP(): string
{
    $candidates = [
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['HTTP_X_REAL_IP'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $raw) {
        if (!is_string($raw)) {
            continue;
        }

        $raw = trim($raw);
        if ($raw === '') {
            continue;
        }

        // X-Forwarded-For 可能是 "client, proxy1, proxy2"，只取第一个
        $first = trim(explode(',', $raw)[0] ?? '');
        if ($first === '') {
            continue;
        }

        $ip = $first;

        // 处理 "[IPv6]:port"
        if (strlen($ip) > 0 && $ip[0] === '[') {
            $end = strpos($ip, ']');
            if ($end !== false) {
                $ip = substr($ip, 1, $end - 1);
            }
        } else {
            // 处理 "IPv4:port"（避免误伤 IPv6）
            if (substr_count($ip, ':') === 1 && strpos($ip, '.') !== false) {
                $parts = explode(':', $ip, 2);
                $ip = $parts[0];
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return substr($ip, 0, 45);
        }

        // 无法验证时也不要直接返回超长内容
        return substr($first, 0, 45);
    }

    return 'unknown';
}
