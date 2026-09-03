<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/threads.php';

try {
    $t = cfg()['threads'];
    if (($t['app_id'] ?? '') === '') throw new RuntimeException('THREADS_APP_ID missing');

    $configured = array_filter(array_map('trim', explode(',', (string)($t['scopes'] ?? ''))));
    $required = [
        'threads_basic',
        'threads_content_publish',
        'threads_manage_replies',
        'threads_read_replies',
        'threads_manage_insights',
        'threads_keyword_search',
        'threads_profile_discovery',
    ];
    $scopes = implode(',', array_values(array_unique(array_merge($configured, $required))));

    $state = bin2hex(random_bytes(16));
    file_put_contents(storage_path('oauth_state.txt'), $state, LOCK_EX);
    $url = $t['auth_url'] . '?' . http_build_query([
        'client_id' => $t['app_id'],
        'redirect_uri' => $t['redirect_uri'],
        'scope' => $scopes,
        'response_type' => 'code',
        'state' => $state,
    ]);

    header('Location: ' . $url, true, 302);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Threads OAuth start error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
