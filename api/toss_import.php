<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/toss.php';

function manmo_value(array $item, string $wanted): mixed {
    if (array_key_exists($wanted, $item)) return $item[$wanted];
    $needle = strtolower(preg_replace('/[^a-z0-9]/i', '', $wanted) ?? '');
    foreach ($item as $key => $value) {
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$key) ?? '');
        if ($normalized === $needle) return $value;
    }
    return null;
}

function manmo_id(array $item): string {
    $v = manmo_value($item, 'tacaltItemId');
    if (is_int($v) || is_float($v)) return (string)$v;
    $s = trim((string)$v);
    return $s !== '' ? $s : '';
}

function manmo_tacald(array $item): string {
    foreach (['tacald', 'productId'] as $key) {
        $v = trim((string)(manmo_value($item, $key) ?? ''));
        if ($v !== '' && ctype_digit($v)) return $v;
    }
    $url = (string)(manmo_value($item, 'productUrl') ?? '');
    if (preg_match('~/t/(\d+)(?:[/?#]|$)~', $url, $m)) return $m[1];
    return '';
}

function manmo_fetch_list(string $source, int $size, string $categoryId, string $cursor): array {
    $q = ['size' => $size];
    if ($cursor !== '') $q['cursor'] = $cursor;
    if ($source === 'today-deals') return toss_api_request('GET', '/openapi/products/today-deals', null, $q);
    if ($source === 'category') {
        if ($categoryId === '') throw new InvalidArgumentException('categoryId required');
        return toss_api_request('GET', '/openapi/products/best-categories/' . rawurlencode($categoryId), null, $q);
    }
    return toss_api_request('GET', '/openapi/products/best-selling', null, $q);
}

/**
 * Resolve one Toss list item to a usable tacaltItemId.
 * Order:
 * 1) list response ID
 * 2) product detail by tacald extracted from productUrl
 * 3) as a final compatibility probe, ask the link API with tacald. If Toss accepts
 *    it, use the ID returned by the link response and reuse that already-issued link.
 */
function manmo_resolve_item(array $item): array {
    $itemId = manmo_id($item);
    $tacald = manmo_tacald($item);
    $detailDebug = null;
    $preissuedLink = null;

    if ($itemId === '' && $tacald !== '') {
        try {
            $detail = toss_product_details_by_tacalds([$tacald]);
            $detailItems = is_array($detail['success']['items'] ?? null) ? $detail['success']['items'] : [];
            $d = $detailItems[0] ?? null;
            $detailDebug = [
                'itemsCount' => count($detailItems),
                'notFoundIds' => $detail['success']['notFoundIds'] ?? [],
                'firstKeys' => is_array($d) ? array_keys($d) : [],
                'firstId' => is_array($d) ? manmo_id($d) : '',
                'firstTacald' => is_array($d) ? manmo_tacald($d) : '',
            ];
            if (is_array($d)) {
                $item = array_replace($item, $d);
                $itemId = manmo_id($item);
            }
        } catch (Throwable $e) {
            $detailDebug = ['error' => $e->getMessage()];
        }
    }

    // Some Toss production responses expose tacald but omit tacaltItemId even in detail.
    // The link endpoint is authoritative: if it accepts the tacald-shaped candidate,
    // its response gives us the monetizable link and usually the resolved option ID.
    if ($itemId === '' && $tacald !== '') {
        try {
            $probe = toss_create_sharelink($tacald);
            $success = is_array($probe['success'] ?? null) ? $probe['success'] : [];
            $short = trim((string)($success['shortUrl'] ?? ''));
            $origin = trim((string)($success['originUrl'] ?? ''));
            if ($short !== '' || $origin !== '') {
                $resolved = trim((string)($success['tacaltItemId'] ?? ''));
                $itemId = $resolved !== '' ? $resolved : $tacald;
                $item['tacaltItemId'] = $itemId;
                $item['tacald'] = $tacald;
                $preissuedLink = $probe;
            }
        } catch (Throwable $e) {
            if (!is_array($detailDebug)) $detailDebug = [];
            $detailDebug['linkProbeError'] = $e->getMessage();
        }
    }

    return [
        'item' => $item,
        'itemId' => $itemId,
        'tacald' => $tacald,
        'preissuedLink' => $preissuedLink,
        'debug' => $detailDebug,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'method_not_allowed'], 405);
require_admin();
$body = request_json();
$source = (string)($body['source'] ?? 'best-selling');
$categoryId = trim((string)($body['category_id'] ?? ''));
$cursor = isset($body['cursor']) ? (string)$body['cursor'] : '';

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

    // Use the documented list size in one request. ID resolution is done per item below.
    $list = manmo_fetch_list($source, $size, $categoryId, $cursor);
    $items = is_array($list['success']['items'] ?? null) ? $list['success']['items'] : [];
    if ($source === 'category') {
        $categoryName = trim((string)($list['success']['category']['displayName'] ?? $categoryName));
    }

    $db = db_read();
    $existingIds = [];
    $existingTacalds = [];
    foreach ($db['products'] as $p) {
        if (!empty($p['tacalt_item_id'])) $existingIds[(string)$p['tacalt_item_id']] = true;
        if (!empty($p['tacald'])) $existingTacalds[(string)$p['tacald']] = true;
    }

    $imported = [];
    $duplicates = 0;
    $soldOut = 0;
    $invalid = 0;
    $expired = 0;
    $diagnostics = [];

    foreach ($items as $rawItem) {
        if (!is_array($rawItem)) { $invalid++; continue; }

        $resolved = manmo_resolve_item($rawItem);
        $item = $resolved['item'];
        $itemId = (string)$resolved['itemId'];
        $tacald = (string)$resolved['tacald'];

        if ($itemId === '') {
            $invalid++;
            if (count($diagnostics) < 3) {
                $diagnostics[] = [
                    'displayName' => manmo_value($rawItem, 'displayName'),
                    'productUrl' => manmo_value($rawItem, 'productUrl'),
                    'listIdValue' => manmo_value($rawItem, 'tacaltItemId'),
                    'tacald' => $tacald,
                    'detail' => $resolved['debug'],
                ];
            }
            continue;
        }

        if (isset($existingIds[$itemId]) || ($tacald !== '' && isset($existingTacalds[$tacald]))) {
            $duplicates++;
            continue;
        }
        if (!empty(manmo_value($item, 'isSoldOut'))) { $soldOut++; continue; }

        $endAt = trim((string)(manmo_value($item, 'endAt') ?? ''));
        if ($endAt !== '' && strtotime($endAt) !== false && strtotime($endAt) <= time()) {
            $expired++;
            continue;
        }

        $link = $resolved['preissuedLink'];
        if (!is_array($link)) $link = toss_create_sharelink($itemId);
        $linkData = is_array($link['success'] ?? null) ? $link['success'] : [];
        $shortUrl = trim((string)($linkData['shortUrl'] ?? ''));
        $originUrl = trim((string)($linkData['originUrl'] ?? ''));
        if ($shortUrl === '' && $originUrl === '') throw new RuntimeException('Toss sharelink missing for item ' . $itemId);

        $discount = (float)(manmo_value($item, 'discountRate') ?? 0);
        $price = (int)(manmo_value($item, 'displayPrice') ?? 0);
        $original = (int)(manmo_value($item, 'originalPrice') ?? 0);
        $rating = manmo_value($item, 'reviewScore');
        $reviews = manmo_value($item, 'reviewCount');
        $descParts = [];
        if ($discount > 0) $descParts[] = '할인율 ' . rtrim(rtrim((string)$discount, '0'), '.') . '%';
        if ($original > 0 && $original !== $price) $descParts[] = '정가 ' . number_format($original) . '원';
        if ($rating !== null) $descParts[] = '평점 ' . $rating;
        if ($reviews !== null) $descParts[] = '리뷰 ' . number_format((int)$reviews) . '개';
        if ($endAt !== '') $descParts[] = '특가 종료 ' . $endAt;

        $descriptionRaw = manmo_value($item, 'description');
        $description = is_array($descriptionRaw) ? $descriptionRaw : [];
        $row = db_insert('products', [
            'name' => trim((string)(manmo_value($item, 'displayName') ?? ('Toss 상품 ' . $itemId))),
            'category' => $categoryName,
            'price' => $price,
            'discount_rate' => $discount,
            'description' => implode(' · ', $descParts),
            'toss_share_url' => $shortUrl !== '' ? $shortUrl : $originUrl,
            'toss_origin_url' => $originUrl,
            'product_url' => trim((string)(manmo_value($item, 'productUrl') ?? '')),
            'thumbnail_url' => trim((string)(manmo_value($item, 'thumbnailUrl') ?? '')),
            'main_image_urls' => is_array(manmo_value($item, 'mainImageUrls')) ? manmo_value($item, 'mainImageUrls') : [],
            'detail_image_urls' => is_array($description['detailImageUrls'] ?? null) ? $description['detailImageUrls'] : [],
            'tacalt_item_id' => $itemId,
            'tacald' => $tacald,
            'rank' => (int)(manmo_value($item, 'rank') ?? 0),
            'category_ids' => is_array(manmo_value($item, 'categoryIds')) ? manmo_value($item, 'categoryIds') : [],
            'is_sold_out' => false,
            'end_at' => $endAt !== '' ? $endAt : null,
            'source' => 'toss_' . str_replace('-', '_', $source),
            'source_category_id' => $source === 'category' ? $categoryId : null,
            'created_at' => date(DATE_ATOM),
        ]);
        $imported[] = $row;
        $existingIds[$itemId] = true;
        if ($tacald !== '') $existingTacalds[$tacald] = true;
        usleep(120000);
    }

    if ($items && $invalid === count($items)) {
        json_response([
            'error' => 'toss_ids_unresolved',
            'message' => 'Toss 목록은 정상이나 옵션 ID 해석에 실패했습니다. 화면의 진단값을 확인해 주세요.',
            'diagnostic' => $diagnostics,
        ], 500);
    }

    json_response([
        'ok' => true,
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
        'has_next' => (bool)($list['success']['hasNext'] ?? false),
        'next_cursor' => $list['success']['nextCursor'] ?? null,
        'products' => $imported,
        'diagnostic' => $diagnostics,
    ]);
} catch (TossApiException $e) {
    json_response(['error' => 'toss_import_failed', 'message' => $e->getMessage(), 'error_code' => $e->errorCode, 'http_status' => $e->httpStatus], 500);
} catch (Throwable $e) {
    json_response(['error' => 'toss_import_failed', 'message' => $e->getMessage()], 500);
}
