<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/threads.php';

$expectedState = is_file(storage_path('oauth_state.txt')) ? trim((string)file_get_contents(storage_path('oauth_state.txt'))) : '';
$state = (string)($_GET['state'] ?? '');
$code = (string)($_GET['code'] ?? '');
$error = (string)($_GET['error'] ?? '');

if ($error !== '') {
    http_response_code(400);
    echo '<h1>Threads 연결 실패</h1><p>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}
if ($code === '') {
    http_response_code(400);
    echo '<h1>Threads 연결 실패</h1><p>authorization code가 없습니다.</p>';
    exit;
}
if ($expectedState !== '' && !hash_equals($expectedState, $state)) {
    http_response_code(400);
    echo '<h1>Threads 연결 실패</h1><p>OAuth state가 일치하지 않습니다.</p>';
    exit;
}

try {
    $token = threads_exchange_code($code);
    @unlink(storage_path('oauth_state.txt'));
    $username = htmlspecialchars((string)($token['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><meta charset="utf-8"><title>Threads 연결 완료</title>';
    echo '<style>body{font-family:system-ui;max-width:720px;margin:60px auto;padding:20px}a{color:#111}</style>';
    echo '<h1>Threads 연결 완료</h1><p>@' . $username . ' 계정의 장기 액세스 토큰을 서버 비공개 저장소에 저장했습니다.</p>';
    echo '<p><a href="/">MANMO 대시보드로 돌아가기</a></p>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h1>Threads 토큰 발급 오류</h1><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
}
