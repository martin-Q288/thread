<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';

function toss_headers(): array {
    $t = cfg()['toss'];
    return [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-Access-Key: ' . $t['access_key'],
        'X-Secret-Key: ' . $t['secret_key'],
        'X-Member-Id: ' . $t['member_id'],
    ];
}

function toss_request(string $method, string $path, ?array $payload = null, array $query = []): array {
    $t = cfg()['toss'];
    if ($t['base_url'] === '') throw new RuntimeException('TOSS_API_BASE_URL missing');
    if ($t['access_key'] === '' || $t['secret_key'] === '' || $t['member_id'] === '') {
        throw new RuntimeException('Toss API credentials missing');
    }
    $url = $t['base_url'] . '/' . ltrim($path, '/');
    if ($query) $url .= '?' . http_build_query($query);
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => toss_headers(),
        CURLOPT_TIMEOUT => 30,
    ];
    if ($payload !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $json = json_decode((string)$body, true);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Toss API HTTP ' . $status . ' ' . ($error ?: (string)$body));
    }
    return is_array($json) ? $json : ['raw' => $body];
}

function toss_search_products(array $query = []): array {
    $path = cfg()['toss']['products_path'];
    if ($path === '') throw new RuntimeException('TOSS_PRODUCTS_PATH missing. Set it from the official Sharelink API guide.');
    return toss_request('GET', $path, null, $query);
}

function toss_create_sharelink(array $payload): array {
    $path = cfg()['toss']['sharelink_path'];
    if ($path === '') throw new RuntimeException('TOSS_SHARELINK_PATH missing. Set it from the official Sharelink API guide.');
    return toss_request('POST', $path, $payload);
}
