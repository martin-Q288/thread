<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(db_read()['products']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$body = request_json();
$name = trim((string)($body['name'] ?? ''));
if ($name === '') json_response(['error'=>'name_required'],422);
$row = db_insert('products', [
    'name' => $name,
    'category' => trim((string)($body['category'] ?? '')),
    'price' => (int)($body['price'] ?? 0),
    'discount_rate' => (float)($body['discount_rate'] ?? 0),
    'description' => trim((string)($body['description'] ?? '')),
    'toss_share_url' => trim((string)($body['toss_share_url'] ?? '')),
    'created_at' => date(DATE_ATOM),
]);
json_response($row,201);
