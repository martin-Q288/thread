<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/toss.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$raw = trim((string)($_GET['tacaltItemIds'] ?? ''));
if ($raw === '') json_response(['error'=>'tacaltItemIds_required'],422);
$ids = array_values(array_filter(array_map('trim', explode(',', $raw)), fn($v)=>$v!==''));
try {
    json_response(toss_product_details($ids));
} catch (Throwable $e) {
    json_response(['error'=>'toss_detail_failed','message'=>$e->getMessage()],500);
}
