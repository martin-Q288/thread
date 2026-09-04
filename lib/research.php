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
            if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) $parts[] = (string)$content['text'];
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
    if ($start === false || $end === false || $end <= $start) throw new RuntimeException('OpenAI response did not contain JSON');
    $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
    if (!is_array($decoded)) throw new RuntimeException('OpenAI JSON parse failed');
    return $decoded;
}

function manmo_openai_response(string $input, bool $useWebSearch = true): array {
    $o = cfg()['openai'] ?? [];
    $apiKey = trim((string)($o['api_key'] ?? ''));
    $model = trim((string)($o['model'] ?? 'gpt-5.6-terra'));
    if ($apiKey === '') throw new RuntimeException('OPENAI_API_KEY missing');
    $payload = ['model'=>$model !== '' ? $model : 'gpt-5.6-terra','input'=>$input,'reasoning'=>['effort'=>'low']];
    if ($useWebSearch) {
        $payload['tools'] = [[
            'type'=>'web_search',
            'search_context_size'=>'medium',
            'user_location'=>['type'=>'approximate','country'=>'KR','city'=>'Seoul','region'=>'Seoul'],
        ]];
    }
    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$apiKey],
        CURLOPT_POSTFIELDS=>json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT=>120,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $decoded = json_decode((string)$raw, true);
    if ($status < 200 || $status >= 300 || !is_array($decoded)) {
        $msg = is_array($decoded) ? (string)($decoded['error']['message'] ?? '') : '';
        throw new RuntimeException('OpenAI API failed: '.($msg !== '' ? $msg : ($err !== '' ? $err : 'HTTP '.$status)));
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
    usort($rows, fn($a,$b)=>(int)($b['likes'] ?? 0)<=>(int)($a['likes'] ?? 0));
    return array_slice($rows, 0, max(1,$limit));
}

function manmo_benchmark_prompt_block(array $benchmarks): string {
    $out=[];
    foreach($benchmarks as $i=>$b){
        $out[]=json_encode(['no'=>$i+1,'likes'=>(int)($b['likes']??0),'username'=>(string)($b['username']??''),'text'=>(string)($b['text']??''),'permalink'=>(string)($b['permalink']??'')],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
    return implode("\n",$out);
}

function manmo_product_research_draft(array $product): array {
    $benchmarks = manmo_verified_benchmarks(12);
    $productData = [
        'name'=>(string)($product['name']??''),
        'category'=>(string)($product['category']??''),
        'description'=>(string)($product['description']??''),
        'discount_rate'=>(float)($product['discount_rate']??0),
        'review_score'=>(float)($product['review_score']??0),
        'review_count'=>(int)($product['review_count']??0),
    ];
    $productJson=json_encode($productData,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $benchmarkBlock=manmo_benchmark_prompt_block($benchmarks);
    if($benchmarkBlock==='') $benchmarkBlock='[현재 Meta 앱 검수 대기 중이라 검증된 Threads 벤치마크가 아직 없습니다. 벤치마크를 지어내지 말고 웹 리서치와 아래 MANMO 패턴 라이브러리만 사용하세요.]';

    $prompt=<<<PROMPT
너는 한국 Threads 커머스 콘텐츠 리서처이자 카피라이터다.

MANMO 핵심 페르소나:
- 전체 타깃은 20~40대 한국 여성이다.
- 중심 페르소나는 31세 자취 여성이고, 기존의 식비 절약·맛있는 다이어트·생활비 절약 관심사를 유지한다.
- 단, 제품군을 음식/살림에만 가두지 않는다. 뷰티, 다이소/균일가 생활템, 향/퍼스널케어, 문구, 귀여운 소품, 테크 액세서리, 집꾸미기, 카페감성, 여행·외출 소품, 간편식, 주방·살림, 생필품까지 넓게 본다.
- 공통 구매 트리거는 '보자마자 궁금함', '영상으로 장점이 바로 보임', '작은 돈으로 기분이 좋아짐', '친구에게 보여주고 싶음', '내 생활이 조금 편해짐'이다.
- 광고문구보다 친구가 알려주는 듯한 짧고 자연스러운 구어체에 반응한다.

최근 참고해야 할 실제 바이럴 구조:
- 과일을 넣고 눌러 바로 음료가 되는 병처럼, 1초 만에 사용법이 이해되는 시각적 데모.
- 다이소 향수/마커펜처럼 '가면 보이면 집어볼 만함', '소소한 행복' 같은 발견형 득템.
- 에어팟 케이스처럼 타인이 먼저 물어봤다는 짧은 일상 에피소드 + 의외의 기능.
- 캐릭터/귀여운 소품처럼 제품 기능보다 '센스', '귀여움', '소장욕' 자체가 구매 이유가 되는 제품.

목표:
1) 아래 상품을 실제 인터넷에서 검색해 사람들이 반복해서 말하는 좋은 점, 구매 이유, 재구매 이유, 의외의 장점, 불만을 조사한다.
2) Threads 벤치마크가 있으면 문장을 복사하지 말고 왜 멈춰 읽게 되는지 구조만 분석한다. 없으면 가짜 벤치마크를 만들지 않는다.
3) 상품과 가장 잘 맞는 MANMO 패턴 A/B/C/D/E 또는 벤치마크 기반 변형을 선택해 훅 20개와 최종 초안을 만든다.
4) 매 게시물에는 사용자가 직접 찍은 영상 1개가 반드시 함께 올라간다. 휴대폰으로 5~10초 안에 찍을 수 있는 구체적인 영상 아이디어도 만든다.
5) '이 제품이 20~40대 한국 여성 Threads에서 왜 멈춰 보게 만드는가'를 반드시 판단한다. 시각적 데모가 약한 상품이면 억지로 바이럴 제품처럼 쓰지 않는다.

MANMO 패턴 라이브러리:
패턴 A — 다이어트 식재료/저칼로리 소스
- 다이어트 중 반복되는 욕구나 불편을 먼저 꺼낸다.
- 기존 음식/소스와 비교했을 때 실제로 확인된 의외의 대체 장점을 보여준다.
- 건강효과, 혈당, 체지방 감소 등은 공식 근거가 명확하지 않으면 절대 단정하지 않는다.

패턴 B — 자취 식비 절감/주방·살림 꿀템
- 버리는 돈, 반복되는 귀찮음, 보관 실패처럼 생활 속 손실을 먼저 보여준다.
- 상품은 문제를 해결하는 도구로 뒤늦게 등장시킨다.
- 근거 없는 절약 금액이나 보존 기간을 만들지 않는다.

패턴 C — 초간단 레시피/방법 + 도구 추천
- 유용한 레시피나 방법 자체를 먼저 제공한다.
- 그 과정을 편하게 해주는 제품을 자연스럽게 연결한다.
- 직접 먹어본 것처럼 말하거나 맛을 체험담으로 꾸미지 않는다.

패턴 D — 발견형/소소한 득템/다이소·뷰티·문구
- '이거 보이면 한번 집어볼 만함', '이 가격대에 이런 포인트가?'처럼 발견의 재미를 먼저 준다.
- 귀여움, 향, 색, 질감, 소장욕, 작은 만족처럼 기능표로 설명할 수 없는 구매 이유를 살린다.
- 실제 가격을 본문에 박는 대신, 가격대가 낮다는 사실이 핵심일 때만 '부담 적은 편', '소소하게 사보기 좋은 편'으로 표현한다.

패턴 E — 시각적 반전/남이 먼저 물어봄/테크·소품
- 사용 장면 하나만 봐도 '뭐야 저거?'가 나오는 제품에 쓴다.
- 타인이 먼저 물어봤다는 식의 체험담은 실제 사용자 후기에 그런 사례가 확인될 때만 간접화법으로 사용한다.
- 의외의 기능, 형태 변화, 눌렀을 때/열었을 때/켜졌을 때 달라지는 장면을 영상 핵심으로 잡는다.

절대 규칙:
- 웹 검색을 반드시 사용한다.
- 실제 근거 없는 장점은 만들지 않는다.
- '내가 써봤는데', '먹어봤는데', '우리 집에서 쓰는데' 같은 가짜 체험담 금지.
- 생수·휴지·세제 같은 일상 소모품을 '기능' 목록으로 설명하지 않는다. 생활 상황, 귀찮음, 반복 구매 이유부터 찾는다.
- 메인 게시글에는 정확한 판매가격 숫자를 기본적으로 쓰지 않는다. 가격 자체가 핵심 팩트일 때만 예외적으로 최소 사용한다.
- 할인율도 꼭 필요할 때만 '할인폭이 큰 편', '조건이 괜찮은 편'처럼 표현한다.
- '강력 추천', '무조건 사라', '인생템' 남발 금지.
- 첫 문장은 1~2줄 안에 멈춰 읽게 만들어야 한다.
- 제품명은 첫 문장에 꼭 공개할 필요 없다.
- 짧은 문단과 자연스러운 반말/구어체를 사용한다.
- 링크는 첫 댓글에서 확인하게 하되 억지 댓글 유도를 하지 않는다.
- 벤치마크 원문이 있으면 8단어 이상 연속 복사하지 않는다.
- 의료·건강 관련 효과는 공신력 있는 근거 없이는 작성하지 않는다.
- 영상 아이디어는 별도 장비 없이 휴대폰으로 쉽게 촬영 가능해야 하며, 과장 연출이나 허위 전후 비교를 만들지 않는다.
- 제품 자체가 영상으로 볼 이유가 약하면 visual_score를 낮게 줘라.

상품:
$productJson

Threads 벤치마크:
$benchmarkBlock

반드시 JSON 하나만 출력한다. 마크다운 코드블록 금지.
{
  "selected_pattern":"A|B|C|D|E|BENCHMARK_HYBRID",
  "pattern_reason":"이 상품에 이 패턴이 맞는 이유",
  "audience_fit":{
    "score":0,
    "reason":"20~40대 한국 여성에게 왜 맞는지",
    "visual_score":0,
    "visual_reason":"5~10초 영상으로 왜 볼 만한지"
  },
  "video_idea":{
    "shot":"5~10초 동안 찍을 핵심 장면",
    "sequence":["첫 장면","중간 장면","마지막 장면"],
    "on_screen_text":"영상에 넣을 아주 짧은 문구 또는 빈 문자열"
  },
  "research": {
    "positive_opinions":["실제 반복 장점 5~8개"],
    "curiosity_angles":["의외 포인트/호기심 3~5개"],
    "cautions":["실제 불만/주의점 1~3개"],
    "sources":[{"title":"출처명","url":"https://...","what_it_supports":"어떤 근거인지"}]
  },
  "benchmark_patterns":[{"pattern":"패턴 이름","mechanism":"왜 멈춰 읽는지","application":"이 상품에 어떻게 적용했는지"}],
  "hooks":[{"hook":"훅 문장","hook_type":"curiosity|problem|surprise|social_proof|contrast|target","source_basis":"어떤 실제 장점을 썼는지"}],
  "winner":{"hook":"최종 훅","body":"최종 본문","why":"선정 이유"}
}

score와 visual_score는 0~10 정수로 평가한다.
hooks는 정확히 20개.
최종 body는 모바일 Threads에서 읽기 좋게 3~6개 짧은 문단, 120~450자 정도.
PROMPT;

    $response=manmo_openai_response($prompt,true);
    $result=manmo_extract_json_object(manmo_openai_output_text($response));
    if(!isset($result['hooks'])||!is_array($result['hooks'])||count($result['hooks'])<10) throw new RuntimeException('OpenAI research result missing hooks');
    if(!isset($result['winner']['body'])||trim((string)$result['winner']['body'])==='') throw new RuntimeException('OpenAI research result missing winner body');
    $result['benchmark_count']=count($benchmarks);
    $result['model']=(string)(cfg()['openai']['model']??'gpt-5.6-terra');
    $result['generated_at']=date(DATE_ATOM);
    return $result;
}

function manmo_verify_threads_candidate_with_openai(array $post): array {
    $permalink=trim((string)($post['permalink']??''));
    $text=trim((string)($post['text']??''));
    if($permalink==='') return ['verified'=>false,'likes'=>null,'reason'=>'no_permalink'];
    $prompt=<<<PROMPT
아래 공개 Threads 게시물을 웹에서 직접 확인해라.
URL: $permalink
게시물 텍스트 참고: $text

현재 공개 화면에서 좋아요 수가 10,000개 이상인지 검증한다.
- 실제 좋아요 숫자를 확인할 수 있을 때만 verified=true.
- 숫자를 확인 못 하면 추정하지 말고 verified=false.
- 조회수, 댓글수, 공유수와 좋아요를 혼동하지 마라.
JSON 하나만 출력:
{"verified":true|false,"likes":숫자|null,"reason":"짧은 이유"}
PROMPT;
    try{
        $response=manmo_openai_response($prompt,true);
        $data=manmo_extract_json_object(manmo_openai_output_text($response));
        $likes=isset($data['likes'])&&is_numeric($data['likes'])?(int)$data['likes']:null;
        $verified=!empty($data['verified'])&&$likes!==null&&$likes>=10000;
        return ['verified'=>$verified,'likes'=>$likes,'reason'=>(string)($data['reason']??'')];
    }catch(Throwable $e){
        return ['verified'=>false,'likes'=>null,'reason'=>'openai_verify_failed'];
    }
}
