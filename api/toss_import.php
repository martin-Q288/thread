<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/toss.php';

function manmo_collect_toss_products(string $source, int $targetCount, string $categoryId = '', string $startCursor = ''): array {
    $max = $source === 'today-deals' ? 30 : 100;
    $targetCount = max(1, min($max, $targetCount));
    $query = ['size'=>$targetCount];
    if ($startCursor !== '') $query['cursor'] = $startCursor;

    if ($source === 'today-deals') return toss_today_deals($query);
    if ($source === 'category') {
        if ($categoryId === '') throw new InvalidArgumentException('categoryId required');
        return toss_category_best($categoryId, $query);
    }
    return toss_best_selling($query);
}

function manmo_extract_tacald(array $item): string {
    foreach (['tacald','productId'] as $key) {
        $v = trim((string)($item[$key] ?? ''));
        if ($v !== '' && ctype_digit($v)) return $v;
    }
    $url = (string)($item['productUrl'] ?? '');
    if (preg_match('~/t/(\d+)(?:[/?#]|$)~', $url, $m)) return $m[1];
    return '';
}

function manmo_enrich_missing_tacalt_ids(array $items): array {
    $need = [];
    foreach ($items as $idx=>$item) {
        if (!is_array($item)) continue;
        if (trim((string)($item['tacaltItemId'] ?? '')) !== '') continue;
        $tacald = manmo_extract_tacald($item);
        if ($tacald !== '') $need[$tacald][] = $idx;
    }
    if (!$need) return $items;

    foreach (array_chunk(array_keys($need), 30) as $chunk) {
        $detail = toss_product_details_by_tacalds($chunk);
        $detailItems = is_array($detail['success']['items'] ?? null) ? $detail['success']['items'] : [];
        foreach ($detailItems as $d) {
            if (!is_array($d)) continue;
            $tacald = trim((string)($d['tacald'] ?? ''));
            if ($tacald === '') $tacald = manmo_extract_tacald($d);
            if ($tacald === '' || empty($need[$tacald])) continue;
            foreach ($need[$tacald] as $idx) {
                $items[$idx] = array_replace($items[$idx], $d);
            }
        }
    }
    return $items;
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
    $items = manmo_enrich_missing_tacalt_ids($items);

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
        if (!is_array($item)) { $invalid++; continue; }
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

        $description = is_array($item['description'] ?? null) ? $item['description'] : [];
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
            'main_image_urls' => is_array($item['mainImageUrls'] ?? null) ? $item['mainImageUrls'] : [],
            'detail_image_urls' => is_array($description['detailImageUrls'] ?? null) ? $description['detailImageUrls'] : [],
            'tacalt_item_id' => $itemId,
            'tacald' => trim((string)($item['tacald'] ?? manmo_extract_tacald($item))),
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
