<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/toss.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();

try {
    // Bypass list cache so this reflects the current Toss response.
    $list = toss_api_request('GET', '/openapi/products/best-selling', null, ['size'=>1]);
    $item = $list['success']['items'][0] ?? null;
    if (!is_array($item)) json_response(['error'=>'no_item','message'=>'Toss best-selling returned no item'],200);

    $productUrl = (string)($item['productUrl'] ?? '');
    $pathId = '';
    if (preg_match('~/t/(\d+)(?:[/?#]|$)~', $productUrl, $m)) $pathId = $m[1];

    $detail = null;
    $detailError = null;
    if ($pathId !== '') {
        try {
            $detail = toss_product_details_by_tacalds([$pathId]);
        } catch (Throwable $e) {
            $detailError = $e->getMessage();
        }
    }

    $detailItems = is_array($detail['success']['items'] ?? null) ? $detail['success']['items'] : [];
    $firstDetail = $detailItems[0] ?? null;

    json_response([
        'ok'=>true,
        'list_item_keys'=>array_keys($item),
        'list_tacalt_item_id'=>$item['tacaltItemId'] ?? null,
        'product_url'=>$productUrl,
        'extracted_path_id'=>$pathId,
        'detail_error'=>$detailError,
        'detail_items_count'=>count($detailItems),
        'detail_first_keys'=>is_array($firstDetail) ? array_keys($firstDetail) : [],
        'detail_tacalt_item_id'=>is_array($firstDetail) ? ($firstDetail['tacaltItemId'] ?? null) : null,
        'detail_tacald'=>is_array($firstDetail) ? ($firstDetail['tacald'] ?? null) : null,
        'not_found_ids'=>$detail['success']['notFoundIds'] ?? [],
    ]);
} catch (TossApiException $e) {
    json_response(['error'=>'toss_debug_failed','message'=>$e->getMessage(),'error_code'=>$e->errorCode,'http_status'=>$e->httpStatus],500);
} catch (Throwable $e) {
    json_response(['error'=>'toss_debug_failed','message'=>$e->getMessage()],500);
}
