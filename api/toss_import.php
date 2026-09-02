<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/toss.php';

function manmo_collect_toss_products(string $source, int $targetCount, string $categoryId = '', string $startCursor = ''): array {
    $max = $source === 'today-deals' ? 30 : 100;
    $targetCount = max(1, min($max, $targetCount));
    $cursor = $startCursor;
    $items = [];
    $lastSuccess = ['items'=>[], 'hasNext'=>false, 'nextCursor'=>null];
    $category = null;
    $seenCursors = [];

    while (count($items) < $targetCount) {
        $query = ['size'=>1];
        if ($cursor !== '') $query['cursor'] = $cursor;

        if ($source === 'today-deals') {
            $page = toss_today_deals($query);
        } elseif ($source === 'category') {
            if ($categoryId === '') throw new InvalidArgumentException('categoryId required');
            $page = toss_category_best($categoryId, $query);
        } else {
            $page = toss_best_selling($query);
        }

        $success = is_array($page['success'] ?? null) ? $page['success'] : [];
        $pageItems = is_array($success['items'] ?? null) ? $success['items'] : [];
        foreach ($pageItems as $item) {
            if (!is_array($item)) continue;
            $items[] = $item;
            if (count($items) >= $targetCount) break;
        }

        if ($category === null && is_array($success['category'] ?? null)) $category = $success['category'];
        $lastSuccess = $success;
        $hasNext = (bool)($success['hasNext'] ?? false);
        $nextCursor = (string)($success['nextCursor'] ?? '');
        if (!$hasNext || $nextCursor === '') break;
        if (isset($seenCursors[$nextCursor])) break;
        $seenCursors[$nextCursor] = true;
        $cursor = $nextCursor;
        usleep(150000);
    }

    $out = [
        'items' => $items,
        'hasNext' => (bool)($lastSuccess['hasNext'] ?? false),
        'nextCursor' => $lastSuccess['nextCursor'] ?? null,
    ];
    if ($category !== null) $out['category'] = $category;
    return ['resultType'=>'SUCCESS', 'success'=>$out];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$body = request_json();
$source = (string)($body['source'] ?? 'best-selling');
$categoryId = trim((string)($body['category_id'] ?? ''));
$cursor = isset($body['cursor']) ? (string)$body['cursor'] : '';

try {
    $health = toss_health();

    if ($source === 'today-deals') {
        $size = max(1, min(30, (int)($body['size'] ?? 10)));
        $list = manmo_collect_toss_products('today-deals', $size, '', $cursor);
        $categoryName = '토스 하루특가';
    } elseif ($source === 'category') {
        if ($categoryId === '') json_response(['error'=>'category_id_required'],422);
        $size = max(1, min(100, (int)($body['size'] ?? 10)));
        $list = manmo_collect_toss_products('category', $size, $categoryId, $cursor);
        $categoryName = trim((string)($list['success']['category']['displayName'] ?? ('카테고리 ' . $categoryId)));
    } else {
        $source = 'best-selling';
        $size = max(1, min(100, (int)($body['size'] ?? 10)));
        $list = manmo_collect_toss_products('best-selling', $size, '', $cursor);
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
    $duplicates = 0;
    $soldOut = 0;
    $invalid = 0;
    $expired = 0;

    foreach ($items as $item) {
        $itemId = trim((string)($item['tacaltItemId'] ?? ''));
        if ($itemId === '') { $invalid++; continue; }
        if (isset($existingIds[$itemId])) { $duplicates++; continue; }
        if (!empty($item['isSoldOut'])) { $soldOut++; continue; }

        $endAt = trim((string)($item['endAt'] ?? ''));
        if ($endAt !== '' && strtotime($endAt) !== false && strtotime($endAt) <= time()) {
            $expired++;
            continue;
        }

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
        if ($endAt !== '') $descParts[] = '특가 종료 ' . $endAt;

        $row = db_insert('products', [
            'name' => trim((string)($item['displayName'] ?? ('Toss 상품 ' . $itemId))),
            'category' => $categoryName,
            'price' => $price,
            'discount_rate' => $discount,
            'description' => implode(' · ', $descParts),
            'toss_share_url' => $shortUrl !== '' ? $shortUrl : $originUrl,
            'toss_origin_url' => $originUrl,
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
        'duplicates' => $duplicates,
        'sold_out' => $soldOut,
        'invalid' => $invalid,
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
