<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/threads_discovery.php';
require_once dirname(__DIR__) . '/lib/research.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$body = request_json();
$queries = $body['queries'] ?? [
    '자취템', '살림템', '꿀템', '특가', '댓글 링크', '공구', '득템', '생활용품 추천'
];
if (!is_array($queries) || !$queries) $queries = ['자취템','살림템','꿀템','특가'];
$perQuery = max(5, min(30, (int)($body['limit_per_query'] ?? 15)));
$openaiVerifyLimit = max(0, min(30, (int)($body['openai_verify_limit'] ?? 12)));

$tokenDebug = threads_debug_current_token();
$scopes = is_array($tokenDebug['scopes'] ?? null) ? $tokenDebug['scopes'] : [];
$hasKeywordScope = in_array('threads_keyword_search', $scopes, true);

if (!empty($tokenDebug['ok']) && !$hasKeywordScope) {
    json_response([
        'ok' => false,
        'error' => 'threads_keyword_search_permission_missing',
        'message' => '현재 Threads 토큰에 threads_keyword_search 권한이 없습니다. 검색 권한 재연결 후 다시 실행하세요.',
        'token_debug' => $tokenDebug,
        'searched' => 0,
        'commerce_candidates' => 0,
        'verified_10k_found' => 0,
        'saved' => 0,
        'verified_total' => count(manmo_verified_benchmarks(10000)),
    ]);
}

$db = db_read();
$existing = [];
foreach (($db['benchmarks'] ?? []) as $b) {
    $key = (string)($b['thread_id'] ?? $b['permalink'] ?? '');
    if ($key !== '') $existing[$key] = true;
}

$searched = 0;
$commerceCandidates = 0;
$verifiedFound = 0;
$saved = 0;
$insightsFailed = 0;
$openaiVerified = 0;
$openaiChecks = 0;
$errors = [];
$candidates = [];
$seenCandidates = [];
$searchDiagnostics = [];

foreach (array_slice($queries, 0, 12) as $query) {
    $query = trim((string)$query);
    if ($query === '') continue;

    $result = null;
    $usedSearchType = 'TOP';
    try {
        $result = threads_keyword_search($query, 'TOP', $perQuery);
        $topCount = count($result['data'] ?? []);
        if ($topCount === 0) {
            $usedSearchType = 'RECENT';
            $result = threads_keyword_search($query, 'RECENT', $perQuery);
        }
        $searchDiagnostics[] = [
            'query' => $query,
            'search_type' => $usedSearchType,
            'count' => count($result['data'] ?? []),
        ];
    } catch (Throwable $e) {
        $errors[] = ['query'=>$query,'search_type'=>$usedSearchType,'error'=>$e->getMessage()];
        continue;
    }

    foreach (($result['data'] ?? []) as $post) {
        $searched++;
        $text = trim((string)($post['text'] ?? ''));
        if ($text === '' || !threads_commerce_signal($text)) continue;
        $commerceCandidates++;

        $threadId = (string)($post['id'] ?? '');
        $permalink = (string)($post['permalink'] ?? '');
        $key = $threadId !== '' ? $threadId : $permalink;
        if ($key === '' || isset($existing[$key]) || isset($seenCandidates[$key])) continue;
        $seenCandidates[$key] = true;

        $metrics = threads_try_public_post_metrics($threadId);
        $likes = $metrics['likes'];
        $verificationMethod = 'threads_insights';
        $verificationReason = '';

        if (!$metrics['verified'] || $likes === null) {
            $insightsFailed++;
            if ($openaiChecks < $openaiVerifyLimit && trim((string)(cfg()['openai']['api_key'] ?? '')) !== '') {
                $openaiChecks++;
                $check = manmo_verify_threads_candidate_with_openai($post);
                if (!empty($check['verified'])) {
                    $likes = (int)$check['likes'];
                    $verificationMethod = 'openai_web_verify';
                    $verificationReason = (string)($check['reason'] ?? '');
                    $openaiVerified++;
                }
            }
        }

        $candidate = [
            'thread_id' => $threadId,
            'username' => (string)($post['username'] ?? ''),
            'text' => $text,
            'permalink' => $permalink,
            'timestamp' => $post['timestamp'] ?? null,
            'query' => $query,
            'likes' => $likes,
            'metrics_error' => $metrics['error'] ?? null,
        ];
        $candidates[] = $candidate;

        if ($likes === null || $likes < 10000) continue;
        $verifiedFound++;

        $record = [
            'thread_id' => $threadId,
            'username' => (string)($post['username'] ?? ''),
            'text' => $text,
            'permalink' => $permalink,
            'timestamp' => $post['timestamp'] ?? null,
            'likes' => (int)$likes,
            'replies' => $metrics['replies'] ?? null,
            'reposts' => $metrics['reposts'] ?? null,
            'quotes' => $metrics['quotes'] ?? null,
            'source_query' => $query,
            'verification_status' => 'verified',
            'verification_method' => $verificationMethod,
            'verification_reason' => $verificationReason,
            'collected_at' => date(DATE_ATOM),
        ];
        db_insert('benchmarks', $record);
        $existing[$key] = true;
        $saved++;
    }
}

$ok = $searched > 0 || count($errors) === 0;
$message = null;
if ($searched === 0 && count($errors) > 0) {
    $message = 'Threads 검색 API 호출이 실패했습니다. 첫 오류: ' . (string)($errors[0]['error'] ?? 'unknown');
} elseif ($searched === 0) {
    $message = 'Threads 검색 API는 응답했지만 TOP/RECENT 모두 검색 결과가 0건이었습니다.';
}

json_response([
    'ok' => $ok,
    'error' => $ok ? null : 'threads_keyword_search_failed',
    'message' => $message,
    'searched' => $searched,
    'commerce_candidates' => $commerceCandidates,
    'verified_10k_found' => $verifiedFound,
    'saved' => $saved,
    'insights_failed' => $insightsFailed,
    'openai_checks' => $openaiChecks,
    'openai_verified' => $openaiVerified,
    'verified_total' => count(manmo_verified_benchmarks(10000)),
    'token_debug' => $tokenDebug,
    'has_keyword_scope' => $hasKeywordScope,
    'errors' => $errors,
    'search_diagnostics' => $searchDiagnostics,
    'sample_candidates' => array_slice($candidates, 0, 10),
]);
