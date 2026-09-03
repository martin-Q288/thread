<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';

final class TossApiException extends RuntimeException {
    public int $httpStatus;
    public ?string $errorCode;
    public ?int $retryAfter;
    public function __construct(string $message, int $httpStatus = 0, ?string $errorCode = null, ?int $retryAfter = null) {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->errorCode = $errorCode;
        $this->retryAfter = $retryAfter;
    }
}

function toss_curl(string $url, array $opts): array {
    $headers = [];
    $ch = curl_init($url);
    $base = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADERFUNCTION => static function($ch, string $line) use (&$headers): int {
            $len = strlen($line);
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            return $len;
        },
    ];
    curl_setopt_array($ch, $opts + $base);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) throw new TossApiException('Toss network error: ' . $error, $status);
    $json = json_decode((string)$body, true);
    $retryAfter = isset($headers['retry-after']) && ctype_digit((string)$headers['retry-after']) ? (int)$headers['retry-after'] : null;
    if ($status < 200 || $status >= 300) {
        $code = is_array($json) ? (string)($json['error']['errorCode'] ?? '') : '';
        throw new TossApiException('Toss API HTTP ' . $status . ' ' . ($error ?: (string)$body), $status, $code !== '' ? $code : null, $retryAfter);
    }
    if (!is_array($json)) throw new TossApiException('Toss API invalid JSON response', $status);
    return ['json'=>$json, 'status'=>$status, 'headers'=>$headers];
}

function toss_cache_file(string $key): string { return storage_path('toss_cache_' . hash('sha256', $key) . '.json'); }
function toss_cache_get(string $key, int $ttl): ?array {
    $file = toss_cache_file($key);
    if (!is_file($file) || filemtime($file) < time() - $ttl) return null;
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : null;
}
function toss_cache_set(string $key, array $data): void {
    file_put_contents(toss_cache_file($key), json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
}
function toss_product_list_cache_valid(array $data): bool {
    $items = $data['success']['items'] ?? null;
    if (!is_array($items)) return false;
    if ($items === []) return true;
    return true;
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
    $raw = toss_curl($t['token_url'], [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $t['access_key'],
            'client_secret' => $t['secret_key'],
            'scope' => $t['scope'],
        ]),
    ]);
    $response = $raw['json'];
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

function toss_api_request(string $method, string $path, ?array $payload = null, array $query = [], int $attempt = 0): array {
    $t = cfg()['toss'];
    $url = $t['base_url'] . '/' . ltrim($path, '/');
    if ($query) $url .= '?' . http_build_query($query);
    $token = toss_access_token(false);
    $opts = [CURLOPT_CUSTOMREQUEST => strtoupper($method), CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $token]];
    if ($payload !== null) {
        $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
    try {
        $raw = toss_curl($url, $opts);
    } catch (TossApiException $e) {
        if ($e->httpStatus === 401 && $attempt < 1) {
            toss_access_token(true);
            return toss_api_request($method, $path, $payload, $query, $attempt + 1);
        }
        if (($e->httpStatus === 429 || $e->httpStatus >= 500) && $attempt < 3) {
            $wait = $e->retryAfter ?? min(8, 1 << $attempt);
            sleep(max(1, $wait));
            return toss_api_request($method, $path, $payload, $query, $attempt + 1);
        }
        throw $e;
    }
    $result = $raw['json'];
    if (($result['resultType'] ?? '') === 'FAIL') {
        $err = is_array($result['error'] ?? null) ? $result['error'] : [];
        $code = (string)($err['errorCode'] ?? 'FAIL');
        $reason = (string)($err['reason'] ?? $err['message'] ?? 'request failed');
        throw new TossApiException('Toss ' . $code . ' ' . $reason, (int)$raw['status'], $code);
    }
    if (($result['resultType'] ?? 'SUCCESS') !== 'SUCCESS') throw new TossApiException('Toss unexpected resultType', (int)$raw['status']);
    return $result;
}

function toss_health(): array { return toss_api_request('GET', cfg()['toss']['health_path']); }
function toss_search_products(array $query = []): array { return toss_best_selling($query); }
function toss_best_selling(array $query = []): array {
    if (isset($query['size'])) $query['size'] = max(1, min(100, (int)$query['size']));
    $key = 'v3:best-selling:' . json_encode($query);
    if (($cached = toss_cache_get($key, 3600)) && toss_product_list_cache_valid($cached)) return $cached;
    $result = toss_api_request('GET', '/openapi/products/best-selling', null, $query);
    toss_cache_set($key, $result);
    return $result;
}
function toss_today_deals(array $query = []): array {
    if (isset($query['size'])) $query['size'] = max(1, min(30, (int)$query['size']));
    $key = 'v3:today-deals:' . json_encode($query);
    if (($cached = toss_cache_get($key, 300)) && toss_product_list_cache_valid($cached)) return $cached;
    $result = toss_api_request('GET', '/openapi/products/today-deals', null, $query);
    toss_cache_set($key, $result);
    return $result;
}
function toss_category_best(int|string $categoryId, array $query = []): array {
    if ((string)$categoryId === '') throw new RuntimeException('categoryId required');
    if (isset($query['size'])) $query['size'] = max(1, min(100, (int)$query['size']));
    $key = 'v3:category:' . (string)$categoryId . ':' . json_encode($query);
    if (($cached = toss_cache_get($key, 21600)) && toss_product_list_cache_valid($cached)) return $cached;
    $result = toss_api_request('GET', '/openapi/products/best-categories/' . rawurlencode((string)$categoryId), null, $query);
    toss_cache_set($key, $result);
    return $result;
}

function toss_product_details(array $tacaltItemIds): array {
    $ids = array_values(array_unique(array_filter(array_map(fn($v)=>(string)$v, $tacaltItemIds), fn($v)=>$v!=='')));
    if (!$ids) throw new InvalidArgumentException('tacaltItemIds required');
    if (count($ids) > 30) throw new InvalidArgumentException('maximum 30 tacaltItemIds');
    return toss_api_request('GET', cfg()['toss']['detail_path'], null, ['tacaltItemIds' => implode(',', $ids)]);
}

function toss_product_details_by_tacalds(array $tacalds): array {
    $ids = array_values(array_unique(array_filter(array_map(fn($v)=>(string)$v, $tacalds), fn($v)=>$v!=='')));
    if (!$ids) throw new InvalidArgumentException('tacalds required');
    if (count($ids) > 30) throw new InvalidArgumentException('maximum 30 tacalds');
    return toss_api_request('GET', cfg()['toss']['detail_path'], null, ['tacalds' => implode(',', $ids)]);
}

function toss_create_sharelink(int|string $itemId): array {
    static $lastIssuedAt = 0.0;
    $publisherId = cfg()['toss']['publisher_id'];
    if ($publisherId === '') throw new RuntimeException('TOSS_PUBLISHER_ID/TOSS_MEMBER_ID missing');

    $value = is_numeric($itemId) ? (int)$itemId : $itemId;
    $fieldNames = ['tacaltItemId', 'tacaItemId', 'tacalItemId'];
    $lastError = null;

    foreach ($fieldNames as $fieldName) {
        $elapsed = microtime(true) - $lastIssuedAt;
        if ($lastIssuedAt > 0 && $elapsed < 0.12) usleep((int)((0.12 - $elapsed) * 1000000));
        try {
            $result = toss_api_request('POST', cfg()['toss']['sharelink_path'], [
                $fieldName => $value,
                'publisherId' => $publisherId,
            ]);
            $lastIssuedAt = microtime(true);
            return $result;
        } catch (TossApiException $e) {
            $lastIssuedAt = microtime(true);
            $lastError = $e;
            $code = strtoupper((string)$e->errorCode);
            if ($e->httpStatus === 401 || $e->httpStatus === 403 || $e->httpStatus === 429 || str_contains($code, 'ACCESS_DENIED') || str_contains($code, 'QUOTA_EXCEEDED')) {
                throw $e;
            }
        }
    }

    if ($lastError instanceof TossApiException) throw $lastError;
    throw new TossApiException('Toss sharelink issuance failed');
}

function toss_performance(string $fromDate, string $toDate, array $query = []): array {
    $from = DateTimeImmutable::createFromFormat('Y-m-d', $fromDate);
    $to = DateTimeImmutable::createFromFormat('Y-m-d', $toDate);
    if (!$from || !$to || $fromDate > $toDate) throw new InvalidArgumentException('invalid performance date range');
    if ($from->diff($to)->days > 30) throw new InvalidArgumentException('performance range maximum is 31 days inclusive');
    $q = ['fromDate'=>$fromDate, 'toDate'=>$toDate] + $query;
    if (isset($q['size'])) $q['size'] = max(1, min(100, (int)$q['size']));
    return toss_api_request('GET', cfg()['toss']['performance_path'], null, $q);
}

function toss_settlement(string $settlementMonth, array $query = []): array {
    if (!preg_match('/^\d{4}-\d{2}$/', $settlementMonth)) throw new InvalidArgumentException('settlementMonth must be YYYY-MM');
    if (isset($query['size'])) $query['size'] = max(1, min(100, (int)$query['size']));
    return toss_api_request('GET', rtrim(cfg()['toss']['settlements_path'], '/') . '/' . rawurlencode($settlementMonth), null, $query);
}
