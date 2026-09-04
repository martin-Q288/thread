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

function manmo_toss_audience_fit(array $item, string $categoryName): array {
    $name = mb_strtolower(trim((string)($item['displayName'] ?? '')) . ' ' . $categoryName);
    $score = 3;
    $bucket = 'general';

    $groups = [
        'beauty' => ['화장품','립','틴트','쿠션','크림','앰플','세럼','마스크팩','선크림','샴푸','트리트먼트','향수','바디','클렌징','네일','헤어'],
        'cute_lifestyle' => ['캐릭터','키링','파우치','인형','스티커','마커','펜','문구','노트','다이어리','텀블러','컵','무드등','소품','쿠로미','산리오','디즈니','토이스토리'],
        'tech_accessory' => ['에어팟','이어폰','케이스','충전기','보조배터리','거치대','스마트폰','아이폰','갤럭시','키보드','마우스'],
        'home_kitchen' => ['주방','용기','보관','수납','정리','밀폐','텀블러','도마','칼','프라이팬','냄비','컵','청소','세제','빨래','행거','선반'],
        'food_diet' => ['다이어트','저당','제로','알룰로스','닭가슴살','프로틴','단백질','간식','간편식','냉동','샐러드','곤약','요거트','견과','커피','차','음료'],
        'travel_outdoor' => ['여행','파우치','캐리어','보틀','우산','선풍기','휴대용','미니','가방','슬리퍼'],
    ];

    foreach ($groups as $group => $words) {
        foreach ($words as $w) {
            if (mb_stripos($name, $w) !== false) {
                $score += 2;
                if ($bucket === 'general') $bucket = $group;
            }
        }
    }

    foreach (['신상','한정','세트','미니','귀여','컬러','디자인','휴대','무선','접이','원터치','자동','리필','대용량','무라벨'] as $w) {
        if (mb_stripos($name, $w) !== false) $score += 1;
    }

    $discount = (float)($item['discountRate'] ?? 0);
    $reviews = (int)($item['reviewCount'] ?? 0);
    $rating = (float)($item['reviewScore'] ?? 0);
    if ($discount >= 20) $score += 1;
    if ($reviews >= 500) $score += 1;
    if ($rating >= 4.7) $score += 1;

    $negative = ['남성','남자','면도기','자동차용','차량용','낚시','공구세트','산업용','군용'];
    foreach ($negative as $w) if (mb_stripos($name, $w) !== false) $score -= 3;

    $score = max(0, min(10, $score));
    return ['score'=>$score,'bucket'=>$bucket];
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
                    $fit = manmo_toss_audience_fit($item, $categoryName);

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
                        'audience_score' => (int)$fit['score'],
                        'audience_bucket' => (string)$fit['bucket'],
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

    usort($imported, fn($a,$b) => (int)($b['audience_score'] ?? 0) <=> (int)($a['audience_score'] ?? 0));

    json_response([
        'ok' => true,
        'version' => 'toss-import-v2-manmo-audience-score',
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
    json_response(['error' => 'toss_import_failed', 'message' => $e->getMessage(), 'error_code'=>$e->errorCode, 'http_status'=>$e->httpStatus, 'version'=>'toss-import-v2-manmo-audience-score'], 500);
} catch (Throwable $e) {
    json_response(['error' => 'toss_import_failed', 'message'=>$e->getMessage(), 'version'=>'toss-import-v2-manmo-audience-score'], 500);
}
