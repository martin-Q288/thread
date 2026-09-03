<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $db = db_read();
    $posts = array_reverse($db['posts']);
    json_response($posts);
}

if ($method !== 'POST' && $method !== 'PATCH') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$body = request_json();
$postId = (int)($body['id'] ?? $body['post_id'] ?? 0);
if ($postId <= 0) json_response(['error'=>'post_id_required'],422);

$db = db_read();
$index = null;
foreach (($db['posts'] ?? []) as $i => $p) {
    if ((int)($p['id'] ?? 0) === $postId) { $index = $i; break; }
}
if ($index === null) json_response(['error'=>'post_not_found'],404);
if (($db['posts'][$index]['status'] ?? 'draft') !== 'draft') {
    json_response(['error'=>'post_not_editable','message'=>'이미 발행된 게시물은 MANMO에서 수정할 수 없습니다.'],409);
}

if (array_key_exists('hook', $body)) {
    $hook = trim((string)$body['hook']);
    if ($hook !== '') $db['posts'][$index]['hook'] = $hook;
}
if (array_key_exists('body', $body)) {
    $text = trim((string)$body['body']);
    if ($text === '') json_response(['error'=>'body_required','message'=>'게시글 본문은 비워둘 수 없습니다.'],422);
    $db['posts'][$index]['body'] = $text;
}
if (array_key_exists('first_comment', $body)) {
    $db['posts'][$index]['first_comment'] = trim((string)$body['first_comment']);
}
$db['posts'][$index]['updated_at'] = date(DATE_ATOM);
db_write($db);
json_response(['ok'=>true,'post'=>$db['posts'][$index]]);
