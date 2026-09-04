<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $products = db_read()['products'];
    usort($products, function($a, $b) {
        $scoreCmp = (int)($b['audience_score'] ?? 0) <=> (int)($a['audience_score'] ?? 0);
        if ($scoreCmp !== 0) return $scoreCmp;
        $rankA = (int)($a['rank'] ?? 999999);
        $rankB = (int)($b['rank'] ?? 999999);
        if ($rankA !== $rankB) return $rankA <=> $rankB;
        return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
    });
    json_response($products);
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
    'audience_score' => (int)($body['audience_score'] ?? 0),
    'audience_bucket' => trim((string)($body['audience_bucket'] ?? 'manual')),
    'created_at' => date(DATE_ATOM),
]);
json_response($row,201);
