<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/threads.php';

$db = db_read();
$auth = threads_token_state();
$totals = ['views'=>0,'comments'=>0,'clicks'=>0,'orders'=>0,'revenue'=>0];
foreach ($db['performance'] as $p) {
    $totals['views'] += (int)($p['views'] ?? 0);
    $totals['comments'] += (int)($p['comments'] ?? 0);
    $totals['clicks'] += (int)($p['link_clicks'] ?? 0);
    $totals['orders'] += (int)($p['orders'] ?? 0);
    $totals['revenue'] += (int)($p['revenue'] ?? 0);
}
json_response([
    'threads_connected' => $auth['access_token'] !== '' && $auth['user_id'] !== '',
    'threads_username' => $auth['username'],
    'threads_expires_at' => $auth['expires_at'],
    'products' => count($db['products']),
    'posts' => count($db['posts']),
    'benchmarks' => count($db['benchmarks']),
    'goal' => 20000000,
    'estimated_income' => (int)round($totals['revenue'] * 0.10),
] + $totals);
