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
    foreach ($item as $key => $value) {
        $normalized = strtolower((string)preg_replace('/[^a-z0-9]/i', '', (string)$key));
        if (!in_array($normalized, ['tacaitemid', 'tacalitemid', 'tacaltitemid'], true)) continue;
        if (is_int($value) || is_float($value)) return (string)(int)$value;
        $s = trim((string)$value);
        if ($s !== '' && ctype_digit($s)) return $s;
    }
    return '';
}

function toss_v2_id_debug(array $item): array {
    $rows = [];
    foreach ($item as $key => $value) {
        $normalized = strtolower((string)preg_replace('/[^a-z0-9]/i', '', (string)$key));
        if (strpos($normalized, 'taca') === false && strpos($normalized, 'itemid') === false) continue;
        $rows[] = [
            'key' => (string)$key,
            'key_hex' => bin2hex((string)$key),
            'normalized' => $normalized,
            'value' => $value,
            'value_type' => gettype($value),
        ];
    }
    return $rows;
}

function toss_v2_fetch_one(string $source, string $categoryId, string $cursor): array {
    $q = ['size' => 1];
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
        $target = max(1, min(30, (int)($body['size'] ?? 10)));
        $categoryName = '토스 하루특가';
    } elseif ($source === 'category') {
        if ($categoryId === '') json_response(['error' => 'category_id_required'], 422);
        $target = max(1, min(100, (int)($body['size'] ?? 10)));
        $categoryName = '카테고리 ' . $categoryId;
    } else {
        $source = 'best-selling';
        $target = max(1, min(100, (int)($body['size'] ?? 10)));
        $categoryName = '토스 베스트';
    }

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
    $received = 0;
    $diagnostic = [];
    $currentCursor = $cursor;
    $nextCursor = null;
    $hasNext = false;

    for ($n = 0; $n < $target; $n++) {
        $page = toss_v2_fetch_one($source, $categoryId, $currentCursor);
        if ($source === 'category') {
            $categoryName = trim((string)($page['success']['category']['displayName'] ?? $categoryName));
        }

        $items = is_array($page['success']['items'] ?? null) ? $page['success']['items'] : [];
        $hasNext = (bool)($page['success']['hasNext'] ?? false);
        $nextCursor = is_string($page['success']['nextCursor'] ?? null) ? $page['success']['nextCursor'] : null;
        if (!$items) break;

        $item = $items[0];
        if (!is_array($item)) {
            $invalid++;
        } else {
            $received++;
            $itemId = toss_v2_item_id($item);

            if ($itemId === '') {
                $invalid++;
                if (count($diagnostic) < 3) $diagnostic[] = [
                    'keys' => array_keys($item),
                    'id_candidates' => toss_v2_id_debug($item),
                    'name' => $item['displayName'] ?? null,
                ];
            } elseif (isset($existing[$itemId])) {
                $duplicates++;
            } elseif (!empty($item['isSoldOut'])) {
                $soldOut++;
            } else {
                $endAt = trim((string)($item['endAt'] ?? ''));
                if ($endAt !== '' && strtotime($endAt) !== false && strtotime($endAt) <= time()) {
                    $expired++;
                } else {
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
            }
        }

        if (!$hasNext || !$nextCursor) break;
        $currentCursor = $nextCursor;
        usleep(150000);
    }

    json_response([
        'ok' => true,
        'version' => 'toss-import-v2-taca-id',
        'health' => $health['success']['status'] ?? 'ok',
        'source' => $source,
        'category' => $categoryName,
        'requested' => $target,
        'received' => $received,
        'imported' => count($imported),
        'duplicates' => $duplicates,
        'sold_out' => $soldOut,
        'invalid' => $invalid,
        'expired' => $expired,
        'has_next' => $hasNext,
        'next_cursor' => $nextCursor,
        'products' => $imported,
        'diagnostic' => $diagnostic,
    ]);
} catch (TossApiException $e) {
    json_response(['error' => 'toss_import_failed', 'message' => $e->getMessage(), 'error_code' => $e->errorCode, 'http_status' => $e->httpStatus, 'version' => 'toss-import-v2-taca-id'], 500);
} catch (Throwable $e) {
    json_response(['error' => 'toss_import_failed', 'message' => $e->getMessage(), 'version' => 'toss-import-v2-taca-id'], 500);
}
