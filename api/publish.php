<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/threads.php';
require_once dirname(__DIR__) . '/lib/toss.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$body = request_json();
$postId = (int)($body['post_id'] ?? 0);
$db = db_read();
$index = null;
foreach ($db['posts'] as $i => $p) if ((int)$p['id'] === $postId) { $index = $i; break; }
if ($index === null) json_response(['error'=>'post_not_found'],404);
$post = $db['posts'][$index];

try {
    // Before publishing, re-check the Toss product's latest price / sold-out state.
    // Toss detail API expects `tacalItemIds` or `tacalIds` (not the old typo `tacaltItemIds`).
    $productIndex = null;
    $product = null;
    $productId = (int)($post['product_id'] ?? 0);
    foreach ($db['products'] as $i => $p) {
        if ((int)($p['id'] ?? 0) === $productId) { $productIndex = $i; $product = $p; break; }
    }

    if ($product && !empty($product['tacalt_item_id'])) {
        $tossItemId = trim((string)$product['tacalt_item_id']);
        $detail = null;

        try {
            $detail = toss_api_request('GET', cfg()['toss']['detail_path'], null, [
                'tacalItemIds' => $tossItemId,
            ]);
        } catch (TossApiException $e) {
            // Some Toss records can be keyed by tacalIds instead. Only fall back for an argument/id mismatch.
            $code = strtoupper((string)$e->errorCode);
            if ($code !== 'INVALID_ARGUMENT' && !str_contains(strtoupper($e->getMessage()), 'TACAL')) {
                throw $e;
            }
            $detail = toss_api_request('GET', cfg()['toss']['detail_path'], null, [
                'tacalIds' => $tossItemId,
            ]);
        }

        $items = $detail['success']['items'] ?? [];
        $fresh = is_array($items) && isset($items[0]) && is_array($items[0]) ? $items[0] : null;
        if (!$fresh) json_response(['error'=>'product_unavailable','message'=>'토스 상세 조회에서 상품을 찾지 못했습니다. 발행하지 않았습니다.'],409);
        if (!empty($fresh['isSoldOut'])) json_response(['error'=>'product_sold_out','message'=>'상품이 품절되어 발행하지 않았습니다.'],409);

        if ($productIndex !== null) {
            $db['products'][$productIndex]['price'] = (int)($fresh['displayPrice'] ?? $product['price'] ?? 0);
            $db['products'][$productIndex]['discount_rate'] = (float)($fresh['discountRate'] ?? $product['discount_rate'] ?? 0);
            $db['products'][$productIndex]['thumbnail_url'] = (string)($fresh['thumbnailUrl'] ?? $product['thumbnail_url'] ?? '');
            $db['products'][$productIndex]['main_image_urls'] = is_array($fresh['mainImageUrls'] ?? null) ? $fresh['mainImageUrls'] : [];
            $db['products'][$productIndex]['detail_image_urls'] = is_array($fresh['description']['detailImageUrls'] ?? null) ? $fresh['description']['detailImageUrls'] : [];
            $db['products'][$productIndex]['last_verified_at'] = date(DATE_ATOM);
            db_write($db);
        }
    }

    $result = threads_publish_with_comment((string)$post['body'], (string)$post['first_comment'], 15);
    $db = db_read();
    foreach ($db['posts'] as $i => $p) if ((int)$p['id'] === $postId) { $index = $i; break; }
    $db['posts'][$index]['status'] = 'published';
    $db['posts'][$index]['thread_id'] = $result['thread']['id'] ?? null;
    $db['posts'][$index]['reply_id'] = $result['reply']['id'] ?? null;
    $db['posts'][$index]['published_at'] = date(DATE_ATOM);
    db_write($db);
    json_response(['ok'=>true,'post'=>$db['posts'][$index],'result'=>$result]);
} catch (TossApiException $e) {
    json_response(['error'=>'product_verify_failed','message'=>$e->getMessage(),'error_code'=>$e->errorCode],502);
} catch (Throwable $e) {
    json_response(['error'=>'publish_failed','message'=>$e->getMessage()],500);
}
