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

function manmo_get_item_value(array $item, string $wanted): mixed {
    if (array_key_exists($wanted, $item)) return $item[$wanted];
    $needle = strtolower(preg_replace('/[^a-z0-9]/i', '', $wanted) ?? '');
    foreach ($item as $key=>$value) {
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$key) ?? '');
        if ($normalized === $needle) return $value;
    }
    return null;
}

function manmo_tacalt_item_id(array $item): string {
    $v = manmo_get_item_value($item, 'tacaltItemId');
    if (is_int($v) || is_float($v)) return (string)$v;
    $s = trim((string)$v);
    return $s !== '' ? $s : '';
}

function manmo_extract_tacald(array $item): string {
    foreach (['tacald','productId'] as $key) {
        $v = trim((string)(manmo_get_item_value($item, $key) ?? ''));
        if ($v !== '' && ctype_digit($v)) return $v;
    }
    $url = (string)(manmo_get_item_value($item, 'productUrl') ?? '');
    if (preg_match('~/t/(\d+)(?:[/?#]|$)~', $url, $m)) return $m[1];
    return '';
}

function manmo_enrich_missing_tacalt_ids(array $items): array {
    foreach ($items as $idx=>$item) {
        if (!is_array($item)) continue;
        if (manmo_tacalt_item_id($item) !== '') continue;

        $tacald = manmo_extract_tacald($item);
        if ($tacald === '') continue;

        // Resolve one product at a time. Toss production can omit tacald/tacaltItemId
        // inconsistently in list responses, so positional one-to-one matching is safer
        // than trying to join a 30-item detail response back by a missing field.
        try {
            $detail = toss_product_details_by_tacalds([$tacald]);
            $detailItems = is_array($detail['success']['items'] ?? null) ? $detail['success']['items'] : [];
            $d = $detailItems[0] ?? null;
            if (is_array($d)) {
                $items[$idx] = array_replace($item, $d);
                if (manmo_tacalt_item_id($items[$idx]) === '') {
                    $resolvedId = manmo_tacalt_item_id($d);
                    if ($resolvedId !== '') $items[$idx]['tacaltItemId'] = $resolvedId;
                }
            }
        } catch (Throwable $e) {
            // Keep the original list item; diagnostics below will expose unresolved IDs.
        }
        usleep(120000);
    }
    return $items;
}

function manmo_valid_id_count(array $items): int {
    $n = 0;
    foreach ($items as $item) {
        if (is_array($item) && manmo_tacalt_item_id($item) !== '') $n++;
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
        $itemId = manmo_tacalt_item_id($item);
        if ($itemId === '') {
            $invalid++;
            if (count($invalidSamples) < 3) {
                $keyInfo = [];
                foreach (array_keys($item) as $key) {
                    $keyInfo[] = (string)$key . '(len=' . strlen((string)$key) . ',hex=' . bin2hex((string)$key) . ')';
                }
                $invalidSamples[] = [
                    'keys'=>array_keys($item),
                    'key_debug'=>$keyInfo,
                    'displayName'=>manmo_get_item_value($item, 'displayName'),
                    'productUrl'=>manmo_get_item_value($item, 'productUrl'),
                    'extractedTacald'=>manmo_extract_tacald($item),
                ];
            }
            continue;
        }
        if (isset($existingIds[$itemId])) { $duplicates++; continue; }
        if (!empty(manmo_get_item_value($item, 'isSoldOut'))) { $soldOut++; continue; }

        $endAt = trim((string)(manmo_get_item_value($item, 'endAt') ?? ''));
        if ($endAt !== '' && strtotime($endAt) !== false && strtotime($endAt) <= time()) {
            $expired++;
            continue;
        }

        $link = toss_create_sharelink($itemId);
        $linkData = $link['success'] ?? [];
        $shortUrl = trim((string)($linkData['shortUrl'] ?? ''));
        $originUrl = trim((string)($linkData['originUrl'] ?? ''));
        if ($shortUrl === '' && $originUrl === '') throw new RuntimeException('Toss sharelink missing for item ' . $itemId);

        $discount = (float)(manmo_get_item_value($item, 'discountRate') ?? 0);
        $price = (int)(manmo_get_item_value($item, 'displayPrice') ?? 0);
        $original = (int)(manmo_get_item_value($item, 'originalPrice') ?? 0);
        $rating = manmo_get_item_value($item, 'reviewScore');
        $reviews = manmo_get_item_value($item, 'reviewCount');
        $descParts = [];
        if ($discount > 0) $descParts[] = '할인율 ' . rtrim(rtrim((string)$discount, '0'), '.') . '%';
        if ($original > 0 && $original !== $price) $descParts[] = '정가 ' . number_format($original) . '원';
        if ($rating !== null) $descParts[] = '평점 ' . $rating;
        if ($reviews !== null) $descParts[] = '리뷰 ' . number_format((int)$reviews) . '개';
        if ($endAt !== '') $descParts[] = '특가 종료 ' . $endAt;

        $descriptionRaw = manmo_get_item_value($item, 'description');
        $description = is_array($descriptionRaw) ? $descriptionRaw : [];
        $row = db_insert('products', [
            'name' => trim((string)(manmo_get_item_value($item, 'displayName') ?? ('Toss 상품 ' . $itemId))),
            'category' => $categoryName,
            'price' => $price,
            'discount_rate' => $discount,
            'description' => implode(' · ', $descParts),
            'toss_share_url' => $shortUrl !== '' ? $shortUrl : $originUrl,
            'toss_origin_url' => $originUrl,
            'product_url' => trim((string)(manmo_get_item_value($item, 'productUrl') ?? '')),
            'thumbnail_url' => trim((string)(manmo_get_item_value($item, 'thumbnailUrl') ?? '')),
            'main_image_urls' => is_array(manmo_get_item_value($item, 'mainImageUrls')) ? manmo_get_item_value($item, 'mainImageUrls') : [],
            'detail_image_urls' => is_array($description['detailImageUrls'] ?? null) ? $description['detailImageUrls'] : [],
            'tacalt_item_id' => $itemId,
            'tacald' => trim((string)(manmo_get_item_value($item, 'tacald') ?? manmo_extract_tacald($item))),
            'rank' => (int)(manmo_get_item_value($item, 'rank') ?? 0),
            'category_ids' => is_array(manmo_get_item_value($item, 'categoryIds')) ? manmo_get_item_value($item, 'categoryIds') : [],
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
            'message'=>'Toss 상품 옵션 ID를 아직 확인하지 못했습니다. 첫 상품 keys=['.$keys.'] productUrl='.(string)($sample['productUrl'] ?? '').' extractedTacald='.(string)($sample['extractedTacald'] ?? ''),
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
