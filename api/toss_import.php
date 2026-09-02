<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/toss.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$body = request_json();
$size = max(1, min(20, (int)($body['size'] ?? 10)));

try {
    $health = toss_health();
    $list = toss_search_products(['size' => $size]);
    $items = $list['success']['items'] ?? [];
    if (!is_array($items)) $items = [];

    $db = db_read();
    $existingIds = [];
    foreach ($db['products'] as $p) {
        if (!empty($p['tacalt_item_id'])) $existingIds[(string)$p['tacalt_item_id']] = true;
    }

    $imported = [];
    $skipped = 0;
    foreach ($items as $item) {
        $itemId = (string)($item['tacaltItemId'] ?? '');
        if ($itemId === '' || isset($existingIds[$itemId])) { $skipped++; continue; }

        $link = toss_create_sharelink($itemId);
        $linkData = $link['success'] ?? [];
        $shortUrl = trim((string)($linkData['shortUrl'] ?? ''));
        $originUrl = trim((string)($linkData['originUrl'] ?? ''));
        if ($shortUrl === '' && $originUrl === '') throw new RuntimeException('Toss sharelink missing for item ' . $itemId);

        $discount = (float)($item['discountRate'] ?? 0);
        $price = (int)($item['displayPrice'] ?? 0);
        $original = (int)($item['originalPrice'] ?? 0);
        $rating = $item['reviewScore'] ?? null;
        $reviews = $item['reviewCount'] ?? null;
        $descParts = [];
        if ($discount > 0) $descParts[] = '할인율 ' . rtrim(rtrim((string)$discount, '0'), '.') . '%';
        if ($original > 0 && $original !== $price) $descParts[] = '정가 ' . number_format($original) . '원';
        if ($rating !== null) $descParts[] = '평점 ' . $rating;
        if ($reviews !== null) $descParts[] = '리뷰 ' . number_format((int)$reviews) . '개';

        $row = db_insert('products', [
            'name' => trim((string)($item['displayName'] ?? ('Toss 상품 ' . $itemId))),
            'category' => '토스 베스트',
            'price' => $price,
            'discount_rate' => $discount,
            'description' => implode(' · ', $descParts),
            'toss_share_url' => $shortUrl !== '' ? $shortUrl : $originUrl,
            'toss_origin_url' => $originUrl,
            'product_url' => trim((string)($item['productUrl'] ?? '')),
            'thumbnail_url' => trim((string)($item['thumbnailUrl'] ?? '')),
            'tacalt_item_id' => $itemId,
            'rank' => (int)($item['rank'] ?? 0),
            'category_ids' => $item['categoryIds'] ?? [],
            'is_sold_out' => (bool)($item['isSoldOut'] ?? false),
            'source' => 'toss_best_selling',
            'created_at' => date(DATE_ATOM),
        ]);
        $imported[] = $row;
        $existingIds[$itemId] = true;
    }

    json_response([
        'ok' => true,
        'health' => $health['success']['status'] ?? 'ok',
        'requested' => $size,
        'received' => count($items),
        'imported' => count($imported),
        'skipped' => $skipped,
        'products' => $imported,
    ]);
} catch (Throwable $e) {
    json_response(['error'=>'toss_import_failed','message'=>$e->getMessage()],500);
}
