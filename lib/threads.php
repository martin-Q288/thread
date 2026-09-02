<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';

function http_post_form(string $url, array $fields, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $json = json_decode((string)$body, true);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('HTTP ' . $status . ' ' . ($error ?: (string)$body));
    }
    return is_array($json) ? $json : ['raw' => $body];
}

function http_get_json(string $url, array $query = []): array {
    if ($query) $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($query);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $json = json_decode((string)$body, true);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('HTTP ' . $status . ' ' . ($error ?: (string)$body));
    }
    return is_array($json) ? $json : ['raw' => $body];
}

function threads_save_token(array $token): void {
    file_put_contents(storage_path('threads_auth.json'), json_encode($token, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function threads_token_state(): array {
    $file = storage_path('threads_auth.json');
    $stored = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
    if (!is_array($stored)) $stored = [];
    $cfg = cfg()['threads'];

    $state = [
        'access_token' => (string)($stored['access_token'] ?? $cfg['access_token'] ?? ''),
        'user_id' => (string)($stored['user_id'] ?? $cfg['user_id'] ?? ''),
        'expires_at' => $stored['expires_at'] ?? null,
        'username' => $stored['username'] ?? null,
    ];

    // Tokens entered directly in .env do not have a cached username yet.
    // Resolve it once from Threads /me, then persist only the derived account metadata.
    if (($state['username'] === null || $state['username'] === '') && $state['access_token'] !== '' && $state['user_id'] !== '') {
        try {
            $me = http_get_json($cfg['graph_base'] . '/me', [
                'fields' => 'id,username',
                'access_token' => $state['access_token'],
            ]);
            $resolvedId = (string)($me['id'] ?? '');
            $resolvedUsername = (string)($me['username'] ?? '');
            if ($resolvedId !== '' && $resolvedUsername !== '') {
                $state['user_id'] = $resolvedId;
                $state['username'] = $resolvedUsername;
                $cached = [
                    'user_id' => $resolvedId,
                    'username' => $resolvedUsername,
                    'expires_at' => $state['expires_at'],
                    'updated_at' => date(DATE_ATOM),
                ];
                // Do not copy .env access tokens into a web-managed file unless one was already stored there.
                if (!empty($stored['access_token'])) $cached['access_token'] = $stored['access_token'];
                threads_save_token($cached);
            }
        } catch (Throwable $e) {
            // Account metadata is cosmetic; token connectivity should still remain usable.
        }
    }

    return $state;
}

function threads_authorize_url(): string {
    $t = cfg()['threads'];
    if ($t['app_id'] === '') throw new RuntimeException('THREADS_APP_ID missing');
    $state = bin2hex(random_bytes(16));
    file_put_contents(storage_path('oauth_state.txt'), $state, LOCK_EX);
    return $t['auth_url'] . '?' . http_build_query([
        'client_id' => $t['app_id'],
        'redirect_uri' => $t['redirect_uri'],
        'scope' => $t['scopes'],
        'response_type' => 'code',
        'state' => $state,
    ]);
}

function threads_exchange_code(string $code): array {
    $t = cfg()['threads'];
    if ($t['app_id'] === '' || $t['app_secret'] === '') throw new RuntimeException('Threads app credentials missing');
    $short = http_post_form($t['token_url'], [
        'client_id' => $t['app_id'],
        'client_secret' => $t['app_secret'],
        'grant_type' => 'authorization_code',
        'redirect_uri' => $t['redirect_uri'],
        'code' => $code,
    ]);
    $shortToken = (string)($short['access_token'] ?? '');
    if ($shortToken === '') throw new RuntimeException('No short-lived access token returned');

    $long = http_get_json($t['long_token_url'], [
        'grant_type' => 'th_exchange_token',
        'client_secret' => $t['app_secret'],
        'access_token' => $shortToken,
    ]);
    $access = (string)($long['access_token'] ?? $shortToken);
    $expiresIn = (int)($long['expires_in'] ?? 0);

    $me = http_get_json($t['graph_base'] . '/me', [
        'fields' => 'id,username',
        'access_token' => $access,
    ]);

    $saved = [
        'access_token' => $access,
        'user_id' => (string)($me['id'] ?? ''),
        'username' => $me['username'] ?? null,
        'expires_at' => $expiresIn > 0 ? time() + $expiresIn : null,
        'created_at' => date(DATE_ATOM),
    ];
    threads_save_token($saved);
    return $saved;
}

function threads_publish_text(string $text, ?string $replyToId = null): array {
    $t = cfg()['threads'];
    $auth = threads_token_state();
    if ($auth['access_token'] === '' || $auth['user_id'] === '') throw new RuntimeException('Threads account is not connected');

    $fields = [
        'media_type' => 'TEXT',
        'text' => $text,
        'access_token' => $auth['access_token'],
    ];
    if ($replyToId) $fields['reply_to_id'] = $replyToId;

    $container = http_post_form($t['graph_base'] . '/' . rawurlencode($auth['user_id']) . '/threads', $fields);
    $creationId = (string)($container['id'] ?? '');
    if ($creationId === '') throw new RuntimeException('Threads creation container id missing');

    $published = http_post_form($t['graph_base'] . '/' . rawurlencode($auth['user_id']) . '/threads_publish', [
        'creation_id' => $creationId,
        'access_token' => $auth['access_token'],
    ]);
    return $published + ['creation_id' => $creationId];
}

function threads_publish_with_comment(string $body, string $comment, int $delaySeconds = 15): array {
    $main = threads_publish_text($body);
    $threadId = (string)($main['id'] ?? '');
    if ($threadId === '') throw new RuntimeException('Published thread id missing');
    if ($delaySeconds > 0) sleep(min($delaySeconds, 30));
    $reply = threads_publish_text($comment, $threadId);
    return ['thread' => $main, 'reply' => $reply];
}

function threads_insights(string $mediaId): array {
    $t = cfg()['threads'];
    $auth = threads_token_state();
    return http_get_json($t['graph_base'] . '/' . rawurlencode($mediaId) . '/insights', [
        'metric' => 'views,likes,replies,reposts,quotes',
        'access_token' => $auth['access_token'],
    ]);
}
