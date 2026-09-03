<?php

declare(strict_types=1);

function manmo_load_env(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || substr($line, 0, 1) === '#' || strpos($line, '=') === false) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (!array_key_exists($key, $_ENV) || $_ENV[$key] === '') $_ENV[$key] = $value;
        if (!array_key_exists($key, $_SERVER) || $_SERVER[$key] === '') $_SERVER[$key] = $value;
    }
}

manmo_load_env(dirname(__DIR__) . '/.env');

function envv(string $key, ?string $default = null): ?string {
    $value = $_ENV[$key] ?? ($_SERVER[$key] ?? null);
    if (($value === null || $value === '') && function_exists('getenv')) {
        $fromEnv = getenv($key);
        if ($fromEnv !== false && $fromEnv !== '') $value = $fromEnv;
    }
    return ($value === null || $value === '') ? $default : (string)$value;
}

function cfg(): array {
    static $config;
    if ($config !== null) return $config;

    $config = [
        'app_url' => rtrim((string)envv('APP_URL', 'https://manmo.neocarelab.co.kr'), '/'),
        'admin_key' => (string)envv('MANMO_ADMIN_KEY', ''),
        'timezone' => (string)envv('APP_TIMEZONE', 'Asia/Seoul'),
        'openai' => [
            'api_key' => (string)envv('OPENAI_API_KEY', ''),
            'model' => (string)envv('OPENAI_MODEL', 'gpt-5.6-terra'),
        ],
        'threads' => [
            'app_id' => (string)envv('THREADS_APP_ID', ''),
            'app_secret' => (string)envv('THREADS_APP_SECRET', ''),
            'redirect_uri' => (string)envv('THREADS_REDIRECT_URI', 'https://manmo.neocarelab.co.kr/auth/threads/callback.php'),
            'user_id' => (string)envv('THREADS_USER_ID', ''),
            'access_token' => (string)envv('THREADS_ACCESS_TOKEN', ''),
            'auth_url' => (string)envv('THREADS_AUTH_URL', 'https://threads.net/oauth/authorize'),
            'token_url' => (string)envv('THREADS_TOKEN_URL', 'https://graph.threads.net/oauth/access_token'),
            'long_token_url' => (string)envv('THREADS_LONG_TOKEN_URL', 'https://graph.threads.net/access_token'),
            'graph_base' => rtrim((string)envv('THREADS_GRAPH_BASE', 'https://graph.threads.net/v1.0'), '/'),
            'scopes' => (string)envv('THREADS_SCOPES', 'threads_basic,threads_content_publish,threads_manage_replies,threads_keyword_search'),
        ],
        'toss' => [
            'access_key' => (string)envv('TOSS_ACCESS_KEY', ''),
            'secret_key' => (string)envv('TOSS_SECRET_KEY', ''),
            'publisher_id' => (string)envv('TOSS_PUBLISHER_ID', envv('TOSS_MEMBER_ID', '')),
            'token_url' => (string)envv('TOSS_TOKEN_URL', 'https://oauth2.cert.toss.im/token'),
            'base_url' => rtrim((string)envv('TOSS_API_BASE_URL', 'https://sharelink.toss.im'), '/'),
            'scope' => (string)envv('TOSS_SCOPE', 'sharelink:read sharelink:write'),
            'products_path' => (string)envv('TOSS_PRODUCTS_PATH', '/openapi/products/best-selling'),
            'sharelink_path' => (string)envv('TOSS_SHARELINK_PATH', '/openapi/links'),
            'health_path' => (string)envv('TOSS_HEALTH_PATH', '/openapi/health'),
            'detail_path' => (string)envv('TOSS_DETAIL_PATH', '/openapi/products/detail'),
            'performance_path' => (string)envv('TOSS_PERFORMANCE_PATH', '/openapi/performance'),
            'settlements_path' => (string)envv('TOSS_SETTLEMENTS_PATH', '/openapi/settlements'),
        ],
        'schedule' => [
            'start_hour' => (int)envv('POST_START_HOUR', '8'),
            'end_hour' => (int)envv('POST_END_HOUR', '24'),
            'max_daily' => (int)envv('DAILY_POST_LIMIT', '17'),
        ],
    ];

    date_default_timezone_set($config['timezone']);
    return $config;
}

function storage_path(string $file = ''): string {
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $file ? $dir . '/' . ltrim($file, '/') : $dir;
}

function require_admin(): void {
    $key = cfg()['admin_key'];
    if ($key === '') return;
    $provided = $_SERVER['HTTP_X_MANMO_KEY'] ?? ($_GET['key'] ?? '');
    if (!hash_equals($key, (string)$provided)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
