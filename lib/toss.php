<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';

function toss_curl(string $url, array $opts): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts + [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $json = json_decode((string)$body, true);
    if ($status < 200 || $status >= 300) throw new RuntimeException('Toss API HTTP ' . $status . ' ' . ($error ?: (string)$body));
    if (!is_array($json)) throw new RuntimeException('Toss API invalid JSON response');
    return $json;
}

function toss_token_state(): array {
    $file = storage_path('toss_token.json');
    if (!is_file($file)) return [];
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function toss_access_token(bool $forceRefresh = false): string {
    $t = cfg()['toss'];
    if ($t['access_key'] === '' || $t['secret_key'] === '') throw new RuntimeException('Toss API credentials missing');
    $stored = toss_token_state();
    if (!$forceRefresh && !empty($stored['access_token']) && (int)($stored['expires_at'] ?? 0) > time() + 120) return (string)$stored['access_token'];
    $response = toss_curl($t['token_url'], [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $t['access_key'],
            'client_secret' => $t['secret_key'],
            'scope' => $t['scope'],
        ]),
    ]);
    $token = (string)($response['access_token'] ?? '');
    if ($token === '') throw new RuntimeException('Toss access_token missing');
    $expiresIn = max(300, (int)($response['expires_in'] ?? 3600));
    file_put_contents(storage_path('toss_token.json'), json_encode([
        'access_token' => $token,
        'scope' => $response['scope'] ?? $t['scope'],
        'token_type' => $response['token_type'] ?? 'Bearer',
        'expires_at' => time() + $expiresIn,
        'created_at' => date(DATE_ATOM),
    ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $token;
}

function toss_api_request(string $method, string $path, ?array $payload = null, array $query = [], bool $retry = true): array {
    $t = cfg()['toss'];
    $url = $t['base_url'] . '/' . ltrim($path, '/');
    if ($query) $url .= '?' . http_build_query($query);
    $token = toss_access_token(false);
    $opts = [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $token],
    ];
    if ($payload !== null) {
        $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
    try {
        $result = toss_curl($url, $opts);
    } catch (RuntimeException $e) {
        if ($retry && strpos($e->getMessage(), 'HTTP 401') !== false) {
            toss_access_token(true);
            return toss_api_request($method, $path, $payload, $query, false);
        }
        throw $e;
    }
    if (($result['resultType'] ?? 'SUCCESS') === 'FAIL') {
        $err = $result['error'] ?? [];
        throw new RuntimeException('Toss ' . ($err['errorCode'] ?? 'FAIL') . ' ' . ($err['message'] ?? 'request failed'));
    }
    return $result;
}

function toss_health(): array { return toss_api_request('GET', cfg()['toss']['health_path']); }
function toss_search_products(array $query = []): array { return toss_best_selling($query); }
function toss_best_selling(array $query = []): array { return toss_api_request('GET', '/openapi/products/best-selling', null, $query); }
function toss_today_deals(array $query = []): array { return toss_api_request('GET', '/openapi/products/today-deals', null, $query); }
function toss_category_best(int|string $categoryId, array $query = []): array {
    if ((string)$categoryId === '') throw new RuntimeException('categoryId required');
    return toss_api_request('GET', '/openapi/products/best-categories/' . rawurlencode((string)$categoryId), null, $query);
}

function toss_product_details(array $tacaltItemIds): array {
    $ids = array_values(array_unique(array_filter(array_map(fn($v)=>(string)$v, $tacaltItemIds), fn($v)=>$v!=='')));
    if (!$ids) throw new InvalidArgumentException('tacaltItemIds required');
    if (count($ids) > 30) throw new InvalidArgumentException('maximum 30 tacaltItemIds');
    return toss_api_request('GET', cfg()['toss']['detail_path'], null, ['tacaltItemIds' => implode(',', $ids)]);
}

function toss_create_sharelink(int|string $tacaltItemId): array {
    $publisherId = cfg()['toss']['publisher_id'];
    if ($publisherId === '') throw new RuntimeException('TOSS_PUBLISHER_ID/TOSS_MEMBER_ID missing');
    return toss_api_request('POST', cfg()['toss']['sharelink_path'], [
        'tacaltItemId' => is_numeric($tacaltItemId) ? (int)$tacaltItemId : $tacaltItemId,
        'publisherId' => $publisherId,
    ]);
}

function toss_performance(string $fromDate, string $toDate, array $query = []): array {
    $q = ['fromDate'=>$fromDate, 'toDate'=>$toDate] + $query;
    if (isset($q['size'])) $q['size'] = max(1, min(100, (int)$q['size']));
    return toss_api_request('GET', cfg()['toss']['performance_path'], null, $q);
}

function toss_settlement(string $settlementMonth, array $query = []): array {
    if (!preg_match('/^\d{4}-\d{2}$/', $settlementMonth)) throw new InvalidArgumentException('settlementMonth must be YYYY-MM');
    if (isset($query['size'])) $query['size'] = max(1, min(100, (int)$query['size']));
    return toss_api_request('GET', rtrim(cfg()['toss']['settlements_path'], '/') . '/' . rawurlencode($settlementMonth), null, $query);
}
