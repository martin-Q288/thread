<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/threads.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$body = request_json();
$postId = (int)($body['post_id'] ?? 0);
$db = db_read();
$index = null;
foreach ($db['posts'] as $i => $p) if ((int)$p['id'] === $postId) { $index = $i; break; }
if ($index === null) json_response(['error'=>'post_not_found'],404);
$post = $db['posts'][$index];
try {
    $result = threads_publish_with_comment((string)$post['body'], (string)$post['first_comment'], 15);
    $db['posts'][$index]['status'] = 'published';
    $db['posts'][$index]['thread_id'] = $result['thread']['id'] ?? null;
    $db['posts'][$index]['reply_id'] = $result['reply']['id'] ?? null;
    $db['posts'][$index]['published_at'] = date(DATE_ATOM);
    db_write($db);
    json_response(['ok'=>true,'post'=>$db['posts'][$index],'result'=>$result]);
} catch (Throwable $e) {
    json_response(['error'=>'publish_failed','message'=>$e->getMessage()],500);
}
