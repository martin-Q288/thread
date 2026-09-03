<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/threads.php';
require_once __DIR__ . '/hooks.php';

function manmo_openai_output_text(array $response): string {
    $parts = [];
    foreach (($response['output'] ?? []) as $item) {
        if (($item['type'] ?? '') !== 'message') continue;
        foreach (($item['content'] ?? []) as $content) {
            if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                $parts[] = (string)$content['text'];
            }
        }
    }
    return trim(implode("\n", $parts));
}

function manmo_extract_json_object(string $text): array {
    $text = trim($text);
    $direct = json_decode($text, true);
    if (is_array($direct)) return $direct;

    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) {
        throw new RuntimeException('OpenAI response did not contain JSON');
    }
    $json = substr($text, $start, $end - $start + 1);
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) throw new RuntimeException('OpenAI JSON parse failed');
    return $decoded;
}

function manmo_openai_response(string $input, bool $useWebSearch = true): array {
    $o = cfg()['openai'] ?? [];
    $apiKey = trim((string)($o['api_key'] ?? ''));
    $model = trim((string)($o['model'] ?? 'gpt-5.6-terra'));
    if ($apiKey === '') throw new RuntimeException('OPENAI_API_KEY missing');

    $payload = [
        'model' => $model !== '' ? $model : 'gpt-5.6-terra',
        'input' => $input,
        'reasoning' => ['effort' => 'low'],
    ];
    if ($useWebSearch) {
        $payload['tools'] = [[
            'type' => 'web_search',
            'search_context_size' => 'medium',
            'user_location' => [
                'type' => 'approximate',
                'country' => 'KR',
                'city' => 'Seoul',
                'region' => 'Seoul',
            ],
        ]];
    }

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 120,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode((string)$raw, true);
    if ($status < 200 || $status >= 300 || !is_array($decoded)) {
        $msg = is_array($decoded) ? (string)($decoded['error']['message'] ?? '') : '';
        throw new RuntimeException('OpenAI API failed: ' . ($msg !== '' ? $msg : ($err !== '' ? $err : 'HTTP ' . $status)));
    }
    return $decoded;
}

function manmo_verified_benchmarks(int $limit = 12): array {
    $db = db_read();
    $rows = [];
    foreach (($db['benchmarks'] ?? []) as $row) {
        if ((int)($row['likes'] ?? 0) < 10000) continue;
        if (($row['verification_status'] ?? '') !== 'verified') continue;
        if (trim((string)($row['text'] ?? '')) === '') continue;
        $rows[] = $row;
    }
    usort($rows, fn($a,$b) => (int)($b['likes'] ?? 0) <=> (int)($a['likes'] ?? 0));
    return array_slice($rows, 0, max(1, $limit));
}

function manmo_benchmark_prompt_block(array $benchmarks): string {
    $out = [];
    foreach ($benchmarks as $i => $b) {
        $out[] = json_encode([
            'no' => $i + 1,
            'likes' => (int)($b['likes'] ?? 0),
            'username' => (string)($b['username'] ?? ''),
            'text' => (string)($b['text'] ?? ''),
            'permalink' => (string)($b['permalink'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return implode("\n", $out);
}

function manmo_product_research_draft(array $product): array {
    $benchmarks = manmo_verified_benchmarks(12);
    if (count($benchmarks) < 3) {
        throw new RuntimeException('BENCHMARKS_MISSING: 좋아요 1만 이상으로 검증된 Threads 벤치마크가 3개 이상 필요합니다. 먼저 벤치마크 수집을 실행하세요.');
    }

    $productData = [
        'name' => (string)($product['name'] ?? ''),
        'category' => (string)($product['category'] ?? ''),
        'description' => (string)($product['description'] ?? ''),
        'discount_rate' => (float)($product['discount_rate'] ?? 0),
        'review_score' => (float)($product['review_score'] ?? 0),
        'review_count' => (int)($product['review_count'] ?? 0),
    ];

    $prompt = <<<PROMPT
너는 한국 Threads 커머스 콘텐츠 리서처이자 카피라이터다.

목표:
1) 아래 상품을 실제 인터넷에서 검색해서 사람들이 반복적으로 말하는 좋은 점, 구매 이유, 재구매 이유, 의외의 장점, 자주 나오는 불만을 조사한다.
2) 아래 Threads 벤치마크는 실제 좋아요 10,000개 이상으로 검증된 글이다. 문장을 복사하지 말고, 왜 멈춰 읽게 되는지 구조만 분석한다.
3) 상품 리서치에서 확인된 실제 장점/호기심과, 벤치마크에서 추출한 바이럴 구조를 결합해 Threads 초안을 만든다.

절대 규칙:
- 웹 검색을 반드시 사용한다.
- 실제 근거 없는 장점은 만들지 않는다.
- '내가 써봤는데', '먹어봤는데', '우리 집에서 쓰는데' 같은 가짜 체험담 금지.
- 제품을 기능 목록처럼 설명하지 않는다. 특히 생수·휴지·세제 같은 일상 소모품에 '기능'이라는 관점으로 접근하지 않는다.
- 메인 게시글에는 정확한 판매가격 숫자를 쓰지 않는다.
- 할인율도 꼭 필요한 경우에만 '할인폭이 큰 편', '조건이 괜찮은 편'처럼 표현한다.
- 광고문구처럼 '강력 추천', '무조건 사라', '인생템'을 남발하지 않는다.
- 첫 문장은 1~2줄 안에 멈춰 읽게 만들어야 한다.
- 제품명은 첫 문장에 꼭 공개할 필요 없다. 궁금증을 남길 수 있으면 뒤로 미룬다.
- 사람 말투처럼 짧은 문단, 자연스러운 반말/구어체를 사용한다.
- 댓글 링크를 보게 만드는 이유가 있어야 한다. 단순히 '댓글 확인'만 반복하지 않는다.
- 벤치마크 원문을 8단어 이상 연속으로 복사하지 않는다.

상품:
{$thisProduct = json_encode($productData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)}

좋아요 1만+ Threads 벤치마크:
{$thisBenchmarks = manmo_benchmark_prompt_block($benchmarks)}

반드시 아래 JSON 하나만 출력해라. 마크다운 코드블록 금지.
{
  "research": {
    "positive_opinions": ["실제 반복 장점 5~8개"],
    "curiosity_angles": ["의외 포인트/호기심 3~5개"],
    "cautions": ["실제 불만/주의점 1~3개"],
    "sources": [{"title":"출처명","url":"https://...","what_it_supports":"어떤 근거인지"}]
  },
  "benchmark_patterns": [
    {"pattern":"패턴 이름","mechanism":"왜 멈춰 읽는지","application":"이 상품에 어떻게 적용했는지"}
  ],
  "hooks": [
    {"hook":"훅 문장","hook_type":"curiosity|problem|surprise|social_proof|contrast|target","source_basis":"어떤 실제 장점을 썼는지"}
  ],
  "winner": {
    "hook":"최종 훅",
    "body":"최종 본문",
    "why":"선정 이유"
  }
}

hooks는 정확히 20개를 만들어라.
최종 body는 모바일 Threads에서 읽기 좋게 3~6개 짧은 문단으로 작성하고 120~450자 정도로 한다.
PROMPT;

    $response = manmo_openai_response($prompt, true);
    $text = manmo_openai_output_text($response);
    $result = manmo_extract_json_object($text);

    if (!isset($result['hooks']) || !is_array($result['hooks']) || count($result['hooks']) < 10) {
        throw new RuntimeException('OpenAI research result missing hooks');
    }
    if (!isset($result['winner']['body']) || trim((string)$result['winner']['body']) === '') {
        throw new RuntimeException('OpenAI research result missing winner body');
    }

    $result['benchmark_count'] = count($benchmarks);
    $result['model'] = (string)(cfg()['openai']['model'] ?? 'gpt-5.6-terra');
    $result['generated_at'] = date(DATE_ATOM);
    return $result;
}

function manmo_verify_threads_candidate_with_openai(array $post): array {
    $permalink = trim((string)($post['permalink'] ?? ''));
    $text = trim((string)($post['text'] ?? ''));
    if ($permalink === '') return ['verified' => false, 'likes' => null, 'reason' => 'no_permalink'];

    $prompt = <<<PROMPT
아래 공개 Threads 게시물을 웹에서 직접 확인해라.
URL: {$permalink}
게시물 텍스트 참고: {$text}

목표는 현재 공개 화면에서 좋아요 수가 10,000개 이상인지 검증하는 것이다.
- 실제로 좋아요 숫자를 확인할 수 있을 때만 verified=true.
- 숫자를 확인하지 못하면 추정하지 말고 verified=false.
- 조회수, 댓글수, 공유수와 좋아요를 혼동하지 마라.

JSON 하나만 출력:
{"verified":true|false,"likes":숫자|null,"reason":"짧은 이유"}
PROMPT;

    try {
        $response = manmo_openai_response($prompt, true);
        $data = manmo_extract_json_object(manmo_openai_output_text($response));
        $likes = isset($data['likes']) && is_numeric($data['likes']) ? (int)$data['likes'] : null;
        $verified = !empty($data['verified']) && $likes !== null && $likes >= 10000;
        return ['verified'=>$verified,'likes'=>$likes,'reason'=>(string)($data['reason'] ?? '')];
    } catch (Throwable $e) {
        return ['verified'=>false,'likes'=>null,'reason'=>'openai_verify_failed'];
    }
}
