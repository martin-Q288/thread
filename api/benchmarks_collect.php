<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/threads_discovery.php';
require_once dirname(__DIR__) . '/lib/research.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$body = request_json();
$queries = $body['queries'] ?? [
    '자취템', '살림템', '꿀템', '특가', '할인', '댓글 링크', '공구', '득템',
    '다이어트 추천', '생활용품 추천', '가전 추천', '이거 사라'
];
if (!is_array($queries) || !$queries) $queries = ['자취템','살림템','꿀템','특가'];
$perQuery = max(5, min(50, (int)($body['limit_per_query'] ?? 25)));

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
$errors = [];
$candidates = [];

foreach (array_slice($queries, 0, 20) as $query) {
    $query = trim((string)$query);
    if ($query === '') continue;
    try {
        $result = threads_keyword_search($query, 'TOP', $perQuery);
    } catch (Throwable $e) {
        $errors[] = ['query'=>$query,'error'=>$e->getMessage()];
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
        if ($key === '' || isset($existing[$key])) continue;

        $metrics = threads_try_public_post_metrics($threadId);
        $likes = $metrics['likes'];
        $verificationMethod = 'threads_insights';
        $verificationReason = '';

        if (!$metrics['verified'] || $likes === null) {
            $insightsFailed++;
            if (trim((string)(cfg()['openai']['api_key'] ?? '')) !== '') {
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

json_response([
    'ok' => true,
    'searched' => $searched,
    'commerce_candidates' => $commerceCandidates,
    'verified_10k_found' => $verifiedFound,
    'saved' => $saved,
    'insights_failed' => $insightsFailed,
    'openai_verified' => $openaiVerified,
    'verified_total' => count(manmo_verified_benchmarks(10000)),
    'errors' => $errors,
    'sample_candidates' => array_slice($candidates, 0, 10),
]);
