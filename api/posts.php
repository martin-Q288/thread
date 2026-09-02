<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['error'=>'method_not_allowed'],405);
$db = db_read();
$posts = array_reverse($db['posts']);
json_response($posts);
