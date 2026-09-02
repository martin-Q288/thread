<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/hooks.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$body = request_json();
$productId = (int)($body['product_id'] ?? 0);
$db = db_read();
$product = null;
foreach ($db['products'] as $p) if ((int)$p['id'] === $productId) { $product = $p; break; }
if (!$product) json_response(['error'=>'product_not_found'],404);
$hooks = generate_hooks($product);
$top3 = array_slice($hooks,0,3);
foreach ($top3 as &$h) $h['body'] = build_post_body($product,$h);
unset($h);
$winner = $top3[0];
$post = db_insert('posts', [
    'product_id' => $productId,
    'hook' => $winner['hook'],
    'hook_type' => $winner['hook_type'],
    'body' => $winner['body'],
    'first_comment' => build_first_comment($product),
    'top3' => $top3,
    'all_hooks' => $hooks,
    'status' => 'draft',
    'created_at' => date(DATE_ATOM),
]);
json_response($post,201);
