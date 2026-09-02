<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/toss.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$body = request_json();
$source = (string)($body['source'] ?? 'best-selling');
$categoryId = trim((string)($body['category_id'] ?? ''));
$cursor = isset($body['cursor']) ? (string)$body['cursor'] : '';

try {
    $health = toss_health();
    $query = [];
    if ($cursor !== '') $query['cursor'] = $cursor; // Opaque cursor: pass through unchanged.

    if ($source === 'today-deals') {
        $size = max(1, min(30, (int)($body['size'] ?? 10)));
        $query['size'] = $size;
        $list = toss_today_deals($query);
        $categoryName = '토스 하루특가';
    } elseif ($source === 'category') {
        if ($categoryId === '') json_response(['error'=>'category_id_required'],422);
        $size = max(1, min(100, (int)($body['size'] ?? 10)));
        $query['size'] = $size;
        $list = toss_category_best($categoryId, $query);
        $categoryName = trim((string)($list['success']['category']['displayName'] ?? ('카테고리 ' . $categoryId)));
    } else {
        $source = 'best-selling';
        $size = max(1, min(100, (int)($body['size'] ?? 10)));
        $query['size'] = $size;
        $list = toss_best_selling($query);
        $categoryName = '토스 베스트';
    }

    $items = $list['success']['items'] ?? [];
    if (!is_array($items)) $items = [];

    $db = db_read();
    $existingIds = [];
    foreach ($db['products'] as $p) {
        if (!empty($p['tacalt_item_id'])) $existingIds[(string)$p['tacalt_item_id']] = true;
    }

    $imported = [];
    $skipped = 0;
    $expired = 0;
    foreach ($items as $item) {
        $itemId = (string)($item['tacaltItemId'] ?? '');
        if ($itemId === '' || isset($existingIds[$itemId])) { $skipped++; continue; }
        if (!empty($item['isSoldOut'])) { $skipped++; continue; }

        $endAt = trim((string)($item['endAt'] ?? ''));
        if ($endAt !== '' && strtotime($endAt) !== false && strtotime($endAt) <= time()) {
            $expired++;
            continue;
        }

        // Link quota is counted only for newly issued links. Existing product IDs are skipped above,
        // so we never waste quota re-issuing the same tacaltItemId + publisherId pair.
        $link = toss_create_sharelink($itemId);
        $linkData = $link['success'] ?? [];
        $shortUrl = trim((string)($linkData['shortUrl'] ?? ''));
        $originUrl = trim((string)($linkData['originUrl'] ?? ''));
        if ($shortUrl === '' && $originUrl === '') throw new RuntimeException('Toss sharelink missing for item ' . $itemId);

        $discount = (float)($item['discountRate'] ?? 0);
        $price = (int)($item['displayPrice'] ?? 0); // Toss docs: shipping already included.
        $original = (int)($item['originalPrice'] ?? 0);
        $rating = $item['reviewScore'] ?? null;
        $reviews = $item['reviewCount'] ?? null;
        $descParts = [];
        if ($discount > 0) $descParts[] = '할인율 ' . rtrim(rtrim((string)$discount, '0'), '.') . '%';
        if ($original > 0 && $original !== $price) $descParts[] = '정가 ' . number_format($original) . '원';
        if ($rating !== null) $descParts[] = '평점 ' . $rating;
        if ($reviews !== null) $descParts[] = '리뷰 ' . number_format((int)$reviews) . '개';
        if ($endAt !== '') $descParts[] = '특가 종료 ' . $endAt;

        $row = db_insert('products', [
            'name' => trim((string)($item['displayName'] ?? ('Toss 상품 ' . $itemId))),
            'category' => $categoryName,
            'price' => $price,
            'discount_rate' => $discount,
            'description' => implode(' · ', $descParts),
            'toss_share_url' => $shortUrl !== '' ? $shortUrl : $originUrl,
            'toss_origin_url' => $originUrl,
            // Keep productUrl only as source metadata. Never publish it as an affiliate link.
            'product_url' => trim((string)($item['productUrl'] ?? '')),
            'thumbnail_url' => trim((string)($item['thumbnailUrl'] ?? '')),
            'tacalt_item_id' => $itemId,
            'rank' => (int)($item['rank'] ?? 0),
            'category_ids' => is_array($item['categoryIds'] ?? null) ? $item['categoryIds'] : [],
            'is_sold_out' => false,
            'end_at' => $endAt !== '' ? $endAt : null,
            'source' => 'toss_' . str_replace('-', '_', $source),
            'source_category_id' => $source === 'category' ? $categoryId : null,
            'created_at' => date(DATE_ATOM),
        ]);
        $imported[] = $row;
        $existingIds[$itemId] = true;
    }

    json_response([
        'ok' => true,
        'health' => $health['success']['status'] ?? 'ok',
        'source' => $source,
        'category' => $categoryName,
        'requested' => $size,
        'received' => count($items),
        'imported' => count($imported),
        'skipped' => $skipped,
        'expired' => $expired,
        'has_next' => (bool)($list['success']['hasNext'] ?? false),
        'next_cursor' => $list['success']['nextCursor'] ?? null,
        'products' => $imported,
    ]);
} catch (TossApiException $e) {
    json_response(['error'=>'toss_import_failed','message'=>$e->getMessage(),'error_code'=>$e->errorCode,'http_status'=>$e->httpStatus],500);
} catch (Throwable $e) {
    json_response(['error'=>'toss_import_failed','message'=>$e->getMessage()],500);
}
