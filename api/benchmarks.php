<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$db = db_read();
$rows = $db['benchmarks'] ?? [];
usort($rows, fn($a,$b) => (int)($b['likes'] ?? 0) <=> (int)($a['likes'] ?? 0));
json_response($rows);
