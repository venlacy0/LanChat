<?php
// Security and content helpers

/**
 * Parse markdown safely and preserve single line breaks.
 */
function customParse(string $text, Parsedown $parsedown): string
{
    // Remove control chars except \n and \t
    $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $text);
    // Normalize newlines
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    $html = $parsedown->text($text);

    // Convert single newlines inside paragraphs to <br>
    $html = preg_replace_callback(
        '/<p>(.*?)<\/p>/s',
        function ($matches) {
            $content = $matches[1];
            $content = preg_replace('/\n/', '<br>', $content);
            return '<p>' . $content . '</p>';
        },
        $html
    );

    return $html;
}

/**
 * Parse markdown but allow raw HTML when present (for embeds).
 */
function customParseAllowHtml(string $text, Parsedown $parsedown): string
{
    // Remove control chars except \n and \t
    $text = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $text);
    // Normalize newlines
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // If HTML tags exist, keep raw HTML and let frontend sanitize.
    if (preg_match('/<\s*[a-z][\s\S]*>/i', $text)) {
        return $text;
    }

    $html = $parsedown->text($text);

    // Convert single newlines inside paragraphs to <br>
    $html = preg_replace_callback(
        '/<p>(.*?)<\/p>/s',
        function ($matches) {
            $content = $matches[1];
            $content = preg_replace('/\n/', '<br>', $content);
            return '<p>' . $content . '</p>';
        },
        $html
    );

    return $html;
}

/**
 * Strictly compare CSRF token.
 */
function verify_csrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
