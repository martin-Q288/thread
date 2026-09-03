<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/threads_discovery.php';
require_once dirname(__DIR__) . '/lib/research.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'method_not_allowed'],405);
require_admin();
$body = request_json();

// MANMO audience identity: Korean women in their 20s-40s, especially a 31-year-old
// solo-living persona interested in saving food/living costs, convenient meals,
// tasty diet foods, kitchen/living products and purchase-worthy deals.
$queries = $body['queries'] ?? [
    '자취 식비 절약',
    '자취생 추천템',
    '생활비 절약템',
    '맛있는 다이어트',
    '다이어트 간식 추천',
    '간편식 추천',
    '냉동식품 추천',
    '주방 꿀템',
    '살림템 추천',
    '재구매템',
    '가성비 추천',
    '특가 추천',
    '할인 중',
    '댓글 링크',
    '공구 추천',
    '이거 왜 이제 샀지',
];
if (!is_array($queries) || !$queries) $queries = ['자취 식비 절약','생활비 절약템','맛있는 다이어트','주방 꿀템'];
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

function manmo_identity_score(string $text): array {
    $t = mb_strtolower($text);
    $scores = [
        'target_fit' => 0,
        'commerce_context' => 0,
        'hook_strength' => 0,
        'curiosity_gap' => 0,
        'action_intent' => 0,
        'conversion_potential' => 0,
    ];

    foreach (['자취','살림','식비','생활비','다이어트','간식','간편식','냉동','주방','집','혼자','1인'] as $w) {
        if (mb_stripos($t, $w) !== false) $scores['target_fit'] += 2;
    }
    foreach (['제품','상품','추천','샀','구매','재구매','특가','할인','가격','공구','품절','득템','가성비'] as $w) {
        if (mb_stripos($t, $w) !== false) $scores['commerce_context'] += 2;
    }
    foreach (['진짜','와 ','헐','미쳤','왜 이제','작정','처음','이거','반칙','말이 안','모르겠'] as $w) {
        if (mb_stripos($t, $w) !== false) $scores['hook_strength'] += 2;
    }
    foreach (['왜','이유','근데','알고 보니','의외','따로','뭔지','결국','대체','차이'] as $w) {
        if (mb_stripos($t, $w) !== false) $scores['curiosity_gap'] += 2;
    }
    foreach (['댓글','링크','프로필','여기서','확인','공유','단톡','사라','사봐'] as $w) {
        if (mb_stripos($t, $w) !== false) $scores['action_intent'] += 2;
    }
    foreach (['할인','특가','재구매','추천','가성비','품절','공구','링크','댓글'] as $w) {
        if (mb_stripos($t, $w) !== false) $scores['conversion_potential'] += 2;
    }

    foreach ($scores as $k => $v) $scores[$k] = min(10, $v);
    $total = array_sum($scores);
    return ['scores'=>$scores,'total'=>$total];
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

foreach (array_slice($queries, 0, 16) as $query) {
    $query = trim((string)$query);
    if ($query === '') continue;

    $result = null;
    $usedSearchType = 'TOP';
    $topCount = 0;
    $recentCount = 0;
    try {
        $result = threads_keyword_search($query, 'TOP', $perQuery);
        $topCount = count($result['data'] ?? []);
        if ($topCount === 0) {
            $usedSearchType = 'RECENT';
            $result = threads_keyword_search($query, 'RECENT', $perQuery);
            $recentCount = count($result['data'] ?? []);
        }
        $sample = [];
        foreach (array_slice($result['data'] ?? [], 0, 3) as $p) {
            $sample[] = mb_substr(trim((string)($p['text'] ?? '')), 0, 120);
        }
        $searchDiagnostics[] = [
            'query' => $query,
            'search_type' => $usedSearchType,
            'top_count' => $topCount,
            'recent_count' => $recentCount,
            'count' => count($result['data'] ?? []),
            'sample' => $sample,
        ];
    } catch (Throwable $e) {
        $errors[] = ['query'=>$query,'search_type'=>$usedSearchType,'error'=>$e->getMessage()];
        $searchDiagnostics[] = ['query'=>$query,'search_type'=>$usedSearchType,'count'=>0,'error'=>$e->getMessage()];
        continue;
    }

    foreach (($result['data'] ?? []) as $post) {
        $searched++;
        $text = trim((string)($post['text'] ?? ''));
        if ($text === '') continue;

        $identity = manmo_identity_score($text);
        // Keep a broad enough candidate pool, but require some commercial or MANMO-audience signal.
        if (!threads_commerce_signal($text) && (int)$identity['total'] < 8) continue;
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
            'identity_scores' => $identity['scores'],
            'identity_total' => $identity['total'],
            'metrics_error' => $metrics['error'] ?? null,
        ];
        $candidates[] = $candidate;

        // Keep the strict 10k benchmark standard for now. We only lower it after search works.
        if ($likes === null || $likes < 10000) continue;
        // Also require the post to be relevant enough to MANMO's audience/commercial identity.
        if ((int)$identity['total'] < 10) continue;
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
            'identity_scores' => $identity['scores'],
            'identity_total' => $identity['total'],
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

usort($candidates, fn($a,$b) => (int)($b['identity_total'] ?? 0) <=> (int)($a['identity_total'] ?? 0));

$ok = $searched > 0;
$message = null;
if ($searched === 0) {
    $parts = [];
    foreach (array_slice($searchDiagnostics, 0, 8) as $d) {
        $parts[] = (string)($d['query'] ?? '-') . ':' . (int)($d['count'] ?? 0);
    }
    if (count($errors) > 0) {
        $message = 'Threads 검색 API 호출은 실패/0건 상태입니다. 첫 오류: ' . (string)($errors[0]['error'] ?? 'unknown') . ' | 쿼리별 ' . implode(', ', $parts);
    } else {
        $message = 'Threads API는 응답했지만 MANMO 검색어 TOP/RECENT 결과가 모두 0건입니다. 쿼리별 ' . implode(', ', $parts);
    }
}

json_response([
    'ok' => $ok,
    'error' => $ok ? null : 'threads_keyword_search_zero_results',
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
