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

function manmo_collect_toss_products_one_by_one(string $source, int $targetCount, string $categoryId = '', string $startCursor = ''): array {
    $max = $source === 'today-deals' ? 30 : 100;
    $targetCount = max(1, min($max, $targetCount));
    $items = [];
    $cursor = $startCursor;
    $nextCursor = null;
    $hasNext = false;
    $category = null;

    for ($i = 0; $i < $targetCount; $i++) {
        $query = ['size'=>1];
        if ($cursor !== '') $query['cursor'] = $cursor;

        if ($source === 'today-deals') {
            $page = toss_api_request('GET', '/openapi/products/today-deals', null, $query);
        } elseif ($source === 'category') {
            if ($categoryId === '') throw new InvalidArgumentException('categoryId required');
            $page = toss_api_request('GET', '/openapi/products/best-categories/' . rawurlencode($categoryId), null, $query);
            if (is_array($page['success']['category'] ?? null)) $category = $page['success']['category'];
        } else {
            $page = toss_api_request('GET', '/openapi/products/best-selling', null, $query);
        }

        $pageItems = is_array($page['success']['items'] ?? null) ? $page['success']['items'] : [];
        if (!$pageItems) {
            $hasNext = false;
            $nextCursor = null;
            break;
        }

        foreach ($pageItems as $pageItem) {
            if (is_array($pageItem)) $items[] = $pageItem;
        }

        $hasNext = (bool)($page['success']['hasNext'] ?? false);
        $nextCursor = $page['success']['nextCursor'] ?? null;
        if (!$hasNext || !is_string($nextCursor) || $nextCursor === '') break;
        $cursor = $nextCursor;
        usleep(150000);
    }

    $success = ['items'=>$items, 'nextCursor'=>$nextCursor, 'hasNext'=>$hasNext];
    if ($category !== null) $success['category'] = $category;
    return ['resultType'=>'SUCCESS', 'success'=>$success];
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
        try {
            $detail = toss_product_details_by_tacalds($chunk);
        } catch (Throwable $e) {
            continue;
        }
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

function manmo_valid_id_count(array $items): int {
    $n = 0;
    foreach ($items as $item) {
        if (is_array($item) && trim((string)($item['tacaltItemId'] ?? '')) !== '') $n++;
    }
    return $n;
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

    // Toss production sometimes omits tacaltItemId when size > 1. If that happens,
    // re-fetch the same list one item at a time with the opaque cursor. This path
    // bypasses the list cache and is the most reliable way to obtain the option ID.
    if ($items && manmo_valid_id_count($items) < count($items)) {
        $fallback = manmo_collect_toss_products_one_by_one($source, $size, $categoryId, $cursor);
        $fallbackItems = is_array($fallback['success']['items'] ?? null) ? $fallback['success']['items'] : [];
        $fallbackItems = manmo_enrich_missing_tacalt_ids($fallbackItems);
        if (manmo_valid_id_count($fallbackItems) > manmo_valid_id_count($items)) {
            $items = $fallbackItems;
            $list = $fallback;
            if ($source === 'category') {
                $categoryName = trim((string)($list['success']['category']['displayName'] ?? $categoryName));
            }
        }
    }

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
    $invalidSamples = [];

    foreach ($items as $item) {
        if (!is_array($item)) { $invalid++; continue; }
        $itemId = trim((string)($item['tacaltItemId'] ?? ''));
        if ($itemId === '') {
            $invalid++;
            if (count($invalidSamples) < 3) {
                $invalidSamples[] = [
                    'keys'=>array_keys($item),
                    'displayName'=>$item['displayName'] ?? null,
                    'productUrl'=>$item['productUrl'] ?? null,
                    'extractedTacald'=>manmo_extract_tacald($item),
                ];
            }
            continue;
        }
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

    if ($items && $invalid === count($items)) {
        $sample = $invalidSamples[0] ?? [];
        $keys = isset($sample['keys']) && is_array($sample['keys']) ? implode(',', $sample['keys']) : '';
        json_response([
            'error'=>'toss_ids_unresolved',
            'message'=>'Toss가 상품은 반환했지만 tacaltItemId를 주지 않았습니다. size=1 cursor 재조회와 상세조회 보강까지 실패했습니다. 첫 상품 keys=['.$keys.'] productUrl='.(string)($sample['productUrl'] ?? '').' extractedTacald='.(string)($sample['extractedTacald'] ?? ''),
            'diagnostic'=>$invalidSamples,
        ],500);
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
        'diagnostic' => $invalidSamples,
    ]);
} catch (TossApiException $e) {
    json_response(['error'=>'toss_import_failed','message'=>$e->getMessage(),'error_code'=>$e->errorCode,'http_status'=>$e->httpStatus],500);
} catch (Throwable $e) {
    json_response(['error'=>'toss_import_failed','message'=>$e->getMessage()],500);
}
