<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';

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
