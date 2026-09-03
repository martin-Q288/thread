<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/toss.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'method_not_allowed'], 405);
require_admin();

function probe_http(string $url, array $headers, ?array $jsonBody = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $jsonBody === null ? 'GET' : 'POST',
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($jsonBody !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'body' => $body === false ? '' : (string)$body, 'error' => $error];
}

try {
    $cfg = cfg()['toss'];
    $token = toss_access_token(false);

    $listUrl = $cfg['base_url'] . '/openapi/products/best-selling?size=1';
    $listRaw = probe_http($listUrl, [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ]);

    $decoded = json_decode($listRaw['body'], true);
    $item = is_array($decoded['success']['items'][0] ?? null) ? $decoded['success']['items'][0] : [];

    $keyDiag = [];
    $itemId = '';
    foreach ($item as $key => $value) {
        $keyString = (string)$key;
        $normalized = strtolower((string)preg_replace('/[^a-z0-9]/i', '', $keyString));
        $keyDiag[] = [
            'key' => $keyString,
            'hex' => bin2hex($keyString),
            'normalized' => $normalized,
            'type' => gettype($value),
            'value' => is_scalar($value) || $value === null ? $value : null,
        ];
        if (($normalized === 'tacalitemid' || $normalized === 'tacaltitemid') && (is_int($value) || is_float($value) || (is_string($value) && ctype_digit(trim($value))))) {
            $itemId = (string)(is_string($value) ? trim($value) : (int)$value);
        }
    }

    $regexId = '';
    if (preg_match('/"([^"\\]*(?:\\.[^"\\]*)*itemId)"\s*:\s*(\d+)/i', $listRaw['body'], $m)) {
        $regexId = (string)$m[2];
        if ($itemId === '') $itemId = $regexId;
    }

    $share = null;
    if ($itemId !== '') {
        $shareUrl = $cfg['base_url'] . '/openapi/links';
        $shareRaw = probe_http($shareUrl, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ], [
            'tacaltItemId' => ctype_digit($itemId) ? (int)$itemId : $itemId,
            'publisherId' => $cfg['publisher_id'],
        ]);
        $share = [
            'status' => $shareRaw['status'],
            'error' => $shareRaw['error'],
            'json' => json_decode($shareRaw['body'], true),
            'raw' => $shareRaw['body'],
        ];
    }

    json_response([
        'ok' => true,
        'version' => 'toss-probe-one-v1',
        'list_status' => $listRaw['status'],
        'list_error' => $listRaw['error'],
        'list_raw' => $listRaw['body'],
        'decoded_result_type' => $decoded['resultType'] ?? null,
        'item_keys' => array_keys($item),
        'key_diagnostic' => $keyDiag,
        'extracted_item_id' => $itemId !== '' ? $itemId : null,
        'regex_item_id' => $regexId !== '' ? $regexId : null,
        'sharelink' => $share,
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'version' => 'toss-probe-one-v1',
        'error' => get_class($e),
        'message' => $e->getMessage(),
    ], 500);
}
