<?php

declare(strict_types=1);

require_once __DIR__ . '/threads.php';

function threads_debug_current_token(): array {
    $t = cfg()['threads'];
    $auth = threads_token_state();
    if ($auth['access_token'] === '') {
        return ['ok'=>false,'scopes'=>[],'error'=>'Threads account is not connected'];
    }
    if (($t['app_id'] ?? '') === '' || ($t['app_secret'] ?? '') === '') {
        return ['ok'=>false,'scopes'=>[],'error'=>'Threads app credentials missing'];
    }

    try {
        $raw = http_get_json($t['graph_base'] . '/debug_token', [
            'input_token' => $auth['access_token'],
            'access_token' => $t['app_id'] . '|' . $t['app_secret'],
        ]);
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $scopes = is_array($data['scopes'] ?? null) ? array_values($data['scopes']) : [];
        return [
            'ok' => !empty($data['is_valid']),
            'is_valid' => (bool)($data['is_valid'] ?? false),
            'scopes' => $scopes,
            'expires_at' => $data['expires_at'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'error' => null,
        ];
    } catch (Throwable $e) {
        return ['ok'=>false,'scopes'=>[],'error'=>$e->getMessage()];
    }
}

function threads_keyword_search(string $query, string $searchType = 'TOP', int $limit = 50): array {
    $t = cfg()['threads'];
    $auth = threads_token_state();
    if ($auth['access_token'] === '') throw new RuntimeException('Threads account is not connected');

    $searchType = strtoupper($searchType);
    if (!in_array($searchType, ['TOP','RECENT'], true)) $searchType = 'TOP';
    $limit = max(1, min(50, $limit));

    // Match Meta's documented keyword_search request as closely as possible.
    // KEYWORD is already the default search mode, so do not send search_mode unless TAG search is needed.
    $fields = 'id,media_product_type,media_type,permalink,username,text,timestamp,shortcode,is_quote_post,has_replies';
    return http_get_json($t['graph_base'] . '/keyword_search', [
        'q' => $query,
        'search_type' => $searchType,
        'fields' => $fields,
        'limit' => $limit,
        'access_token' => $auth['access_token'],
    ]);
}

function threads_insight_value(array $response, string $metric): ?int {
    foreach (($response['data'] ?? []) as $row) {
        if (($row['name'] ?? '') !== $metric) continue;
        if (isset($row['total_value']['value']) && is_numeric($row['total_value']['value'])) {
            return (int)$row['total_value']['value'];
        }
        if (isset($row['values'][0]['value']) && is_numeric($row['values'][0]['value'])) {
            return (int)$row['values'][0]['value'];
        }
    }
    return null;
}

function threads_try_public_post_metrics(string $threadId): array {
    if ($threadId === '') return ['likes'=>null,'replies'=>null,'reposts'=>null,'quotes'=>null,'verified'=>false,'error'=>'missing_id'];
    try {
        $raw = threads_insights($threadId);
        return [
            'likes' => threads_insight_value($raw, 'likes'),
            'replies' => threads_insight_value($raw, 'replies'),
            'reposts' => threads_insight_value($raw, 'reposts'),
            'quotes' => threads_insight_value($raw, 'quotes'),
            'verified' => true,
            'error' => null,
        ];
    } catch (Throwable $e) {
        return ['likes'=>null,'replies'=>null,'reposts'=>null,'quotes'=>null,'verified'=>false,'error'=>$e->getMessage()];
    }
}

function threads_commerce_signal(string $text): bool {
    $text = mb_strtolower($text);
    foreach (['댓글','링크','특가','할인','쿠팡','토스','공구','구매','가격','품절','프로모션','추천','사라','사봐','샀','득템','꿀템','자취템','살림템'] as $needle) {
        if (mb_stripos($text, $needle) !== false) return true;
    }
    return false;
}
