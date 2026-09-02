<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/toss.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_admin();
    $fromDate = trim((string)($_GET['fromDate'] ?? date('Y-m-d', strtotime('-7 days'))));
    $toDate = trim((string)($_GET['toDate'] ?? date('Y-m-d')));
    $query = [];
    foreach (['subTagId','attribution','cursor','size'] as $k) if (isset($_GET[$k]) && $_GET[$k] !== '') $query[$k] = $_GET[$k];
    try {
        json_response(toss_performance($fromDate, $toDate, $query));
    } catch (Throwable $e) {
        json_response(['error'=>'toss_performance_failed','message'=>$e->getMessage()],500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$body = request_json();
$postId = (int)($body['post_id'] ?? 0);
if ($postId <= 0) json_response(['error'=>'post_id_required'],422);
$row = db_insert('performance', [
    'post_id' => $postId,
    'views' => (int)($body['views'] ?? 0),
    'likes' => (int)($body['likes'] ?? 0),
    'comments' => (int)($body['comments'] ?? 0),
    'reposts' => (int)($body['reposts'] ?? 0),
    'link_clicks' => (int)($body['link_clicks'] ?? 0),
    'orders' => (int)($body['orders'] ?? 0),
    'revenue' => (int)($body['revenue'] ?? 0),
    'measured_at' => date(DATE_ATOM),
]);
json_response($row,201);
