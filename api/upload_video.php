<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();

$postId = (int)($_POST['post_id'] ?? 0);
if ($postId <= 0) json_response(['error'=>'post_id_required','message'=>'게시물을 먼저 선택하세요.'],422);
if (!isset($_FILES['video']) || !is_array($_FILES['video'])) json_response(['error'=>'video_required','message'=>'영상 파일을 선택하세요.'],422);
$file = $_FILES['video'];
if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $code = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $messages = [
        UPLOAD_ERR_INI_SIZE => '서버의 업로드 허용 용량보다 영상이 큽니다.',
        UPLOAD_ERR_FORM_SIZE => '영상 파일이 너무 큽니다.',
        UPLOAD_ERR_PARTIAL => '영상 업로드가 중간에 끊겼습니다.',
        UPLOAD_ERR_NO_FILE => '영상 파일을 선택하세요.',
    ];
    json_response(['error'=>'upload_failed','message'=>$messages[$code] ?? ('영상 업로드 오류 코드 ' . $code)],422);
}

$size = (int)($file['size'] ?? 0);
if ($size <= 0) json_response(['error'=>'empty_video','message'=>'빈 영상 파일은 업로드할 수 없습니다.'],422);
if ($size > 100 * 1024 * 1024) json_response(['error'=>'video_too_large','message'=>'영상은 100MB 이하로 업로드해 주세요.'],413);

$tmp = (string)($file['tmp_name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) json_response(['error'=>'invalid_upload','message'=>'정상적인 업로드 파일이 아닙니다.'],422);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($tmp);
$allowed = [
    'video/mp4' => 'mp4',
    'video/quicktime' => 'mov',
    'video/x-m4v' => 'm4v',
];
if (!isset($allowed[$mime])) {
    json_response(['error'=>'unsupported_video','message'=>'MP4, MOV 또는 M4V 영상만 업로드할 수 있습니다. 현재 형식: ' . $mime],415);
}

$db = db_read();
$index = null;
foreach (($db['posts'] ?? []) as $i => $post) {
    if ((int)($post['id'] ?? 0) === $postId) { $index = $i; break; }
}
if ($index === null) json_response(['error'=>'post_not_found'],404);
if (($db['posts'][$index]['status'] ?? 'draft') !== 'draft') {
    json_response(['error'=>'post_not_editable','message'=>'이미 발행된 게시물에는 영상을 교체할 수 없습니다.'],409);
}

$dir = dirname(__DIR__) . '/uploads';
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    json_response(['error'=>'upload_dir_failed','message'=>'영상 저장 폴더를 만들지 못했습니다.'],500);
}

$ext = $allowed[$mime];
$name = 'manmo_' . $postId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
$dest = $dir . '/' . $name;
if (!move_uploaded_file($tmp, $dest)) json_response(['error'=>'move_failed','message'=>'영상을 서버에 저장하지 못했습니다.'],500);
@chmod($dest, 0644);

$oldPath = (string)($db['posts'][$index]['video_local_path'] ?? '');
if ($oldPath !== '' && str_starts_with($oldPath, $dir . '/') && is_file($oldPath) && $oldPath !== $dest) @unlink($oldPath);

$publicUrl = rtrim((string)cfg()['app_url'], '/') . '/uploads/' . rawurlencode($name);
$db['posts'][$index]['video_url'] = $publicUrl;
$db['posts'][$index]['video_local_path'] = $dest;
$db['posts'][$index]['video_name'] = (string)($file['name'] ?? $name);
$db['posts'][$index]['video_mime'] = $mime;
$db['posts'][$index]['video_size'] = $size;
$db['posts'][$index]['video_uploaded_at'] = date(DATE_ATOM);
$db['posts'][$index]['updated_at'] = date(DATE_ATOM);
db_write($db);

json_response([
    'ok'=>true,
    'video_url'=>$publicUrl,
    'video_name'=>$db['posts'][$index]['video_name'],
    'video_size'=>$size,
    'post'=>$db['posts'][$index],
]);
