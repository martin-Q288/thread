<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/toss.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'method_not_allowed'], 405);
require_admin();
$body = request_json();
$source = (string)($body['source'] ?? 'best-selling');
$categoryId = trim((string)($body['category_id'] ?? ''));
$cursor = isset($body['cursor']) ? (string)$body['cursor'] : '';

function toss_v2_item_id(array $item): string {
    // Production list response currently uses tacalItemId (one t).
    $v = $item['tacalItemId'] ?? $item['tacaltItemId'] ?? null;
    if (is_int($v) || is_float($v)) return (string)(int)$v;
    $s = trim((string)$v);
    return ($s !== '' && ctype_digit($s)) ? $s : '';
}

function toss_v2_fetch(string $source, string $categoryId, int $size, string $cursor): array {
    $q = ['size' => $size];
    if ($cursor !== '') $q['cursor'] = $cursor;
    if ($source === 'today-deals') return toss_api_request('GET', '/openapi/products/today-deals', null, $q);
    if ($source === 'category') {
        if ($categoryId === '') throw new InvalidArgumentException('categoryId required');
        return toss_api_request('GET', '/openapi/products/best-categories/' . rawurlencode($categoryId), null, $q);
    }
    return toss_api_request('GET', '/openapi/products/best-selling', null, $q);
}

try {
    $health = toss_health();
    if ($source === 'today-deals') {
        $size = max(1, min(30, (int)($body['size'] ?? 10)));
        $categoryName = '토스 하루특가';
    } elseif ($source === 'category') {
        if ($categoryId === '') json_response(['error' => 'category_id_required'], 422);
        $size = max(1, min(100, (int)($body['size'] ?? 10)));
        $categoryName = '카테고리 ' . $categoryId;
    } else {
        $source = 'best-selling';
        $size = max(1, min(100, (int)($body['size'] ?? 10)));
        $categoryName = '토스 베스트';
    }

    $page = toss_v2_fetch($source, $categoryId, $size, $cursor);
    if ($source === 'category') {
        $categoryName = trim((string)($page['success']['category']['displayName'] ?? $categoryName));
    }
    $items = is_array($page['success']['items'] ?? null) ? $page['success']['items'] : [];

    $db = db_read();
    $existing = [];
    foreach (($db['products'] ?? []) as $p) {
        if (!empty($p['tacalt_item_id'])) $existing[(string)$p['tacalt_item_id']] = true;
    }

    $imported = [];
    $duplicates = 0;
    $soldOut = 0;
    $invalid = 0;
    $expired = 0;
    $diagnostic = [];

    foreach ($items as $item) {
        if (!is_array($item)) { $invalid++; continue; }
        $itemId = toss_v2_item_id($item);
        if ($itemId === '') {
            $invalid++;
            if (count($diagnostic) < 3) $diagnostic[] = [
                'keys' => array_keys($item),
                'tacalItemId' => $item['tacalItemId'] ?? null,
                'tacaltItemId' => $item['tacaltItemId'] ?? null,
                'name' => $item['displayName'] ?? null,
            ];
            continue;
        }
        if (isset($existing[$itemId])) { $duplicates++; continue; }
        if (!empty($item['isSoldOut'])) { $soldOut++; continue; }

        $endAt = trim((string)($item['endAt'] ?? ''));
        if ($endAt !== '' && strtotime($endAt) !== false && strtotime($endAt) <= time()) { $expired++; continue; }

        // Link API expects the documented request field tacaltItemId; helper handles that.
        $link = toss_create_sharelink($itemId);
        $linkData = is_array($link['success'] ?? null) ? $link['success'] : [];
        $shortUrl = trim((string)($linkData['shortUrl'] ?? ''));
        $originUrl = trim((string)($linkData['originUrl'] ?? ''));
        if ($shortUrl === '' && $originUrl === '') throw new RuntimeException('Toss sharelink missing for item ' . $itemId);

        $discount = (float)($item['discountRate'] ?? 0);
        $price = (int)($item['displayPrice'] ?? 0);
        $original = (int)($item['originalPrice'] ?? 0);
        $desc = [];
        if ($discount > 0) $desc[] = '할인율 ' . rtrim(rtrim((string)$discount, '0'), '.') . '%';
        if ($original > 0 && $original !== $price) $desc[] = '정가 ' . number_format($original) . '원';
        if (isset($item['reviewScore'])) $desc[] = '평점 ' . $item['reviewScore'];
        if (isset($item['reviewCount'])) $desc[] = '리뷰 ' . number_format((int)$item['reviewCount']) . '개';

        $row = db_insert('products', [
            'name' => trim((string)($item['displayName'] ?? ('Toss 상품 ' . $itemId))),
            'category' => $categoryName,
            'price' => $price,
            'discount_rate' => $discount,
            'description' => implode(' · ', $desc),
            'toss_share_url' => $shortUrl !== '' ? $shortUrl : $originUrl,
            'toss_origin_url' => $originUrl,
            'product_url' => trim((string)($item['productUrl'] ?? '')),
            'thumbnail_url' => trim((string)($item['thumbnailUrl'] ?? '')),
            'main_image_urls' => [],
            'detail_image_urls' => [],
            'tacalt_item_id' => $itemId,
            'tacald' => '',
            'rank' => (int)($item['rank'] ?? 0),
            'category_ids' => is_array($item['categoryIds'] ?? null) ? $item['categoryIds'] : [],
            'is_sold_out' => false,
            'end_at' => $endAt !== '' ? $endAt : null,
            'source' => 'toss_' . str_replace('-', '_', $source),
            'source_category_id' => $source === 'category' ? $categoryId : null,
            'created_at' => date(DATE_ATOM),
        ]);
        $imported[] = $row;
        $existing[$itemId] = true;
    }

    json_response([
        'ok' => true,
        'version' => 'toss-import-v2-direct-tacal',
        'health' => $health['success']['status'] ?? 'ok',
        'source' => $source,
        'category' => $categoryName,
        'requested' => $size,
        'received' => count($items),
        'imported' => count($imported),
        'duplicates' => $duplicates,
        'sold_out' => $soldOut,
        'invalid' => $invalid,
        'expired' => $expired,
        'has_next' => (bool)($page['success']['hasNext'] ?? false),
        'next_cursor' => is_string($page['success']['nextCursor'] ?? null) ? $page['success']['nextCursor'] : null,
        'products' => $imported,
        'diagnostic' => $diagnostic,
    ]);
} catch (TossApiException $e) {
    json_response(['error' => 'toss_import_failed', 'message' => $e->getMessage(), 'error_code' => $e->errorCode, 'http_status' => $e->httpStatus, 'version' => 'toss-import-v2-direct-tacal'], 500);
} catch (Throwable $e) {
    json_response(['error' => 'toss_import_failed', 'message' => $e->getMessage(), 'version' => 'toss-import-v2-direct-tacal'], 500);
}
