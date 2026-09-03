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

function manmo_first_value(array $item, array $aliases): mixed {
    foreach ($aliases as $alias) {
        $v = manmo_value($item, (string)$alias);
        if ($v !== null && $v !== '') return $v;
    }
    return null;
}

function manmo_id(array $item): string {
    // Toss production currently returns `tacalItemId` (one 't'), while docs/examples
    // have also used `tacaltItemId`. Accept both so the beta API can change safely.
    $v = manmo_first_value($item, ['tacalItemId', 'tacaltItemId']);
    if (is_int($v) || is_float($v)) return (string)$v;
    $s = trim((string)$v);
    return ($s !== '' && ctype_digit($s)) ? $s : '';
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

function manmo_fetch_page(string $source, string $categoryId, string $cursor = ''): array {
    $q = ['size' => 1];
    if ($cursor !== '') $q['cursor'] = $cursor;
    if ($source === 'today-deals') return toss_api_request('GET', '/openapi/products/today-deals', null, $q);
    if ($source === 'category') {
        if ($categoryId === '') throw new InvalidArgumentException('categoryId required');
        return toss_api_request('GET', '/openapi/products/best-categories/' . rawurlencode($categoryId), null, $q);
    }
    return toss_api_request('GET', '/openapi/products/best-selling', null, $q);
}

function manmo_detail_candidates(string $candidate): array {
    if ($candidate === '' || !ctype_digit($candidate)) return [];
    $out = [];

    try {
        $r = toss_product_details_by_tacalds([$candidate]);
        foreach (($r['success']['items'] ?? []) as $item) if (is_array($item)) $out[] = $item;
    } catch (Throwable $e) {}

    try {
        $r = toss_product_details([$candidate]);
        foreach (($r['success']['items'] ?? []) as $item) if (is_array($item)) $out[] = $item;
    } catch (Throwable $e) {}

    return $out;
}

function manmo_resolve_item(array $raw): array {
    $item = $raw;
    $id = manmo_id($item);
    $tacald = manmo_tacald($item);
    $debug = [
        'name' => manmo_value($raw, 'displayName'),
        'productUrl' => manmo_value($raw, 'productUrl'),
        'listId' => manmo_first_value($raw, ['tacalItemId', 'tacaltItemId']),
        'tacald' => $tacald,
    ];

    if ($id === '' && $tacald !== '') {
        foreach (manmo_detail_candidates($tacald) as $detail) {
            $candidateId = manmo_id($detail);
            if ($candidateId !== '') {
                $item = array_replace($item, $detail);
                $id = $candidateId;
                break;
            }
        }
    }

    return ['item' => $item, 'id' => $id, 'tacald' => $tacald, 'debug' => $debug];
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
    $existingIds = [];
    $existingTacalds = [];
    foreach (($db['products'] ?? []) as $p) {
        if (!empty($p['tacalt_item_id'])) $existingIds[(string)$p['tacalt_item_id']] = true;
        if (!empty($p['tacald'])) $existingTacalds[(string)$p['tacald']] = true;
    }

    $imported = [];
    $duplicates = 0;
    $soldOut = 0;
    $invalid = 0;
    $expired = 0;
    $scanned = 0;
    $diagnostics = [];
    $currentCursor = $cursor;
    $nextCursor = null;
    $hasNext = false;

    $scanLimit = min(100, max($target * 5, $target));

    for ($i = 0; $i < $scanLimit && count($imported) < $target; $i++) {
        $page = manmo_fetch_page($source, $categoryId, $currentCursor);
        if ($source === 'category') {
            $categoryName = trim((string)($page['success']['category']['displayName'] ?? $categoryName));
        }

        $pageItems = is_array($page['success']['items'] ?? null) ? $page['success']['items'] : [];
        $hasNext = (bool)($page['success']['hasNext'] ?? false);
        $nextCursor = is_string($page['success']['nextCursor'] ?? null) ? $page['success']['nextCursor'] : null;
        if (!$pageItems) break;

        $raw = $pageItems[0];
        if (!is_array($raw)) {
            $invalid++;
        } else {
            $scanned++;
            $resolved = manmo_resolve_item($raw);
            $item = $resolved['item'];
            $itemId = (string)$resolved['id'];
            $tacald = (string)$resolved['tacald'];

            if ($itemId === '') {
                $invalid++;
                if (count($diagnostics) < 5) $diagnostics[] = $resolved['debug'];
            } elseif (isset($existingIds[$itemId]) || ($tacald !== '' && isset($existingTacalds[$tacald]))) {
                $duplicates++;
            } elseif (!empty(manmo_value($item, 'isSoldOut'))) {
                $soldOut++;
            } else {
                $endAt = trim((string)(manmo_value($item, 'endAt') ?? ''));
                if ($endAt !== '' && strtotime($endAt) !== false && strtotime($endAt) <= time()) {
                    $expired++;
                } else {
                    $link = toss_create_sharelink($itemId);
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
                }
            }
        }

        if (!$hasNext || !$nextCursor) break;
        $currentCursor = $nextCursor;
        usleep(150000);
    }

    if (!$imported && $scanned > 0) {
        json_response([
            'error' => 'toss_no_usable_products',
            'message' => 'Toss에서 ' . $scanned . '개 상품을 확인했지만 추적 링크를 만들 수 있는 옵션 ID가 한 건도 내려오지 않았습니다.',
            'requested' => $target,
            'scanned' => $scanned,
            'invalid' => $invalid,
            'diagnostic' => $diagnostics,
        ], 502);
    }

    json_response([
        'ok' => true,
        'health' => $health['success']['status'] ?? 'ok',
        'source' => $source,
        'category' => $categoryName,
        'requested' => $target,
        'received' => $scanned,
        'imported' => count($imported),
        'duplicates' => $duplicates,
        'sold_out' => $soldOut,
        'invalid' => $invalid,
        'expired' => $expired,
        'has_next' => $hasNext,
        'next_cursor' => $nextCursor,
        'products' => $imported,
        'diagnostic' => $diagnostics,
    ]);
} catch (TossApiException $e) {
    json_response(['error' => 'toss_import_failed', 'message' => $e->getMessage(), 'error_code' => $e->errorCode, 'http_status' => $e->httpStatus], 500);
} catch (Throwable $e) {
    json_response(['error' => 'toss_import_failed', 'message' => $e->getMessage()], 500);
}
