<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/toss.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$month = trim((string)($_GET['settlementMonth'] ?? date('Y-m')));
$query = [];
foreach (['subTagId','attribution','cursor','size'] as $k) if (isset($_GET[$k]) && $_GET[$k] !== '') $query[$k] = $_GET[$k];
try {
    json_response(toss_settlement($month, $query));
} catch (Throwable $e) {
    json_response(['error'=>'toss_settlement_failed','message'=>$e->getMessage()],500);
}
