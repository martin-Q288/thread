<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/hooks.php';
require_once dirname(__DIR__) . '/lib/research.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$body = request_json();
$productId = (int)($body['product_id'] ?? 0);
$db = db_read();
$product = null;
foreach ($db['products'] as $p) if ((int)$p['id'] === $productId) { $product = $p; break; }
if (!$product) json_response(['error'=>'product_not_found'],404);

try {
    $research = manmo_product_research_draft($product);
} catch (Throwable $e) {
    $message = $e->getMessage();
    $code = str_starts_with($message, 'BENCHMARKS_MISSING:') ? 'benchmarks_missing' : (str_contains($message, 'OPENAI_API_KEY missing') ? 'openai_key_missing' : 'research_failed');
    json_response(['error'=>$code,'message'=>$message],400);
}

$hooks = [];
foreach (($research['hooks'] ?? []) as $row) {
    $hook = trim((string)($row['hook'] ?? ''));
    if ($hook === '') continue;
    $hooks[] = [
        'hook' => $hook,
        'hook_type' => (string)($row['hook_type'] ?? 'curiosity'),
        'source_basis' => (string)($row['source_basis'] ?? ''),
    ];
}
$winner = $research['winner'] ?? [];
$winnerHook = trim((string)($winner['hook'] ?? ($hooks[0]['hook'] ?? '')));
$winnerBody = trim((string)($winner['body'] ?? ''));
if ($winnerHook === '' || $winnerBody === '') json_response(['error'=>'research_invalid','message'=>'리서치 결과에 최종 훅 또는 본문이 없습니다.'],500);

$top3 = array_slice($hooks, 0, 3);
$post = db_insert('posts', [
    'product_id' => $productId,
    'hook' => $winnerHook,
    'hook_type' => (string)($winner['hook_type'] ?? ($top3[0]['hook_type'] ?? 'curiosity')),
    'body' => $winnerBody,
    'first_comment' => build_first_comment($product),
    'top3' => $top3,
    'all_hooks' => $hooks,
    'research' => $research['research'] ?? [],
    'benchmark_patterns' => $research['benchmark_patterns'] ?? [],
    'benchmark_count' => (int)($research['benchmark_count'] ?? 0),
    'generator_model' => (string)($research['model'] ?? ''),
    'winner_reason' => (string)($winner['why'] ?? ''),
    'status' => 'draft',
    'created_at' => date(DATE_ATOM),
]);
json_response($post,201);
