<?php

declare(strict_types=1);

function manmo_contains(string $text, array $needles): bool {
    foreach ($needles as $needle) {
        if ($needle !== '' && mb_stripos($text, $needle) !== false) return true;
    }
    return false;
}

function manmo_product_kind(array $product): string {
    $text = mb_strtolower(trim((string)($product['name'] ?? '')) . ' ' . trim((string)($product['category'] ?? '')));

    if (manmo_contains($text, ['생수','물 ','워터','탄산수','음료','커피','차 ','주스'])) return 'beverage';
    if (manmo_contains($text, ['닭가슴살','김치','과자','간식','라면','밥','국','탕','죽','만두','식품','고기','육포','견과','프로틴','단백질'])) return 'food';
    if (manmo_contains($text, ['화장지','휴지','키친타올','세제','섬유유연제','물티슈','청소','수세미','쓰레기봉투','생활용품'])) return 'household';
    if (manmo_contains($text, ['모니터','태블릿','스피커','이어폰','충전','가습기','선풍기','청소기','에어프라이어','전기','가전'])) return 'electronics';
    if (manmo_contains($text, ['운동','덤벨','요가','폼롤러','밴드','헬스','러닝','마사지'])) return 'fitness';
    if (manmo_contains($text, ['샴푸','크림','앰플','선크림','화장품','뷰티','마스크팩','클렌징'])) return 'beauty';
    return 'general';
}

function manmo_product_specs(array $product): array {
    $name = trim((string)($product['name'] ?? ''));
    $specs = [];

    if (preg_match_all('/\b\d+(?:\.\d+)?\s?(?:ml|mL|L|l|kg|g|개|팩|롤|매|봉|병|캔|입|정|포|장)\b/u', $name, $m)) {
        foreach ($m[0] as $v) {
            $v = trim($v);
            if ($v !== '' && !in_array($v, $specs, true)) $specs[] = $v;
        }
    }

    foreach (['무라벨','대용량','소포장','개별포장','저당','무설탕','제로','고단백','국산','냉동','냉장'] as $keyword) {
        if (mb_stripos($name, $keyword) !== false && !in_array($keyword, $specs, true)) $specs[] = $keyword;
    }

    return array_slice($specs, 0, 4);
}

function manmo_review_count(array $product): int {
    $desc = (string)($product['description'] ?? '');
    if (preg_match('/리뷰\s*([0-9,]+)개/u', $desc, $m)) return (int)str_replace(',', '', $m[1]);
    return 0;
}

function manmo_rating(array $product): float {
    $desc = (string)($product['description'] ?? '');
    if (preg_match('/평점\s*([0-9.]+)/u', $desc, $m)) return (float)$m[1];
    return 0.0;
}

function manmo_subject(array $product): string {
    $name = trim((string)($product['name'] ?? '이 제품'));
    if ($name === '') return '이 제품';
    $parts = preg_split('/[,·|]/u', $name);
    $subject = trim((string)($parts[0] ?? $name));
    return mb_strlen($subject) > 28 ? mb_substr($subject, 0, 28) . '…' : $subject;
}

function manmo_profile(array $product): array {
    $kind = manmo_product_kind($product);
    $name = trim((string)($product['name'] ?? ''));
    $subject = manmo_subject($product);
    $specs = manmo_product_specs($product);
    $discount = (float)($product['discount_rate'] ?? 0);
    $reviews = manmo_review_count($product);
    $rating = manmo_rating($product);

    $specText = $specs ? implode(' · ', $specs) : '';
    $proof = '';
    if ($reviews >= 10000) $proof = '리뷰가 만 단위로 쌓여 있는 상품';
    elseif ($reviews >= 1000) $proof = '리뷰가 꽤 많이 쌓인 상품';
    elseif ($rating >= 4.7) $proof = '평점이 높은 편인 상품';

    $benefits = match ($kind) {
        'beverage' => [
            '떨어질 때마다 사러 나가는 번거로움을 줄이기 좋다',
            '한 번 주문해두고 집에 쟁여두기 좋은 소모품이다',
            manmo_contains($name, ['무라벨']) ? '빈 병 버릴 때 라벨을 따로 뜯는 수고가 줄어든다' : '매일 손이 가는 품목이라 구성 차이가 체감되기 쉽다',
        ],
        'food' => [
            '배고플 때 바로 집어먹거나 챙기기 쉬운지가 핵심이다',
            '자주 먹는 식품은 맛보다 보관과 구성에서 만족도가 갈린다',
            '한 번 사두고 실제로 끝까지 먹을 수 있는 구성이 중요하다',
        ],
        'household' => [
            '떨어질 때마다 다시 사는 귀찮음을 줄여주는 게 가장 큰 장점이다',
            '매일 쓰는 소모품은 결국 보관과 묶음 구성이 체감 차이를 만든다',
            '생활용품은 화려한 기능보다 손이 덜 가는지가 중요하다',
        ],
        'electronics' => [
            '매일 쓰는 물건일수록 기능보다 실제 사용 동선이 편해지는지가 중요하다',
            '자리만 차지하는 가전과 자주 손이 가는 가전은 편의성에서 갈린다',
            '스펙보다 집에서 얼마나 자주 쓰게 되는지가 핵심이다',
        ],
        'fitness' => [
            '운동용품은 거창한 기능보다 꺼내서 바로 쓸 수 있는지가 더 중요하다',
            '꾸준히 쓰려면 보관과 사용 준비가 번거롭지 않아야 한다',
            '집에서 반복해서 쓰기 편한 구성이 결국 오래 간다',
        ],
        'beauty' => [
            '매일 쓰는 제품은 복잡한 설명보다 사용감과 루틴에 잘 들어오는지가 중요하다',
            '꾸준히 쓰는 제품은 결국 손이 자주 가는지가 만족도를 가른다',
            '화장대에 놓고 실제로 계속 쓰게 되는지가 핵심이다',
        ],
        default => [
            '자주 쓰는 물건은 결국 손이 덜 가는지가 가장 큰 메리트다',
            '구성만 잘 맞으면 반복해서 사는 번거로움을 줄일 수 있다',
            '실제로 자주 쓰는 상황이 분명한 제품인지가 중요하다',
        ],
    };

    return compact('kind','name','subject','specs','specText','discount','reviews','rating','proof','benefits');
}

function hook_score(string $type, string $hook): array {
    $stop = in_array($type, ['surprise','target','curiosity','problem'], true) ? 9 : 8;
    $curiosity = in_array($type, ['surprise','curiosity','problem'], true) ? 9 : 8;
    $human = 9;
    $comment = in_array($type, ['target','curiosity','problem'], true) ? 9 : 8;
    $purchase = in_array($type, ['benefit','target','problem'], true) ? 9 : 8;
    $total = $stop*.30 + $curiosity*.25 + $human*.20 + $comment*.15 + $purchase*.10;
    return compact('stop','curiosity','human','comment','purchase') + ['total' => round($total, 2)];
}

function generate_hooks(array $product): array {
    $p = manmo_profile($product);
    $subject = $p['subject'];
    $spec = $p['specText'];
    $benefit1 = $p['benefits'][0];
    $benefit2 = $p['benefits'][1];
    $kind = $p['kind'];

    $hooks = [];
    $add = function(string $type, string $text) use (&$hooks): void {
        $text = trim($text);
        if ($text === '') return;
        foreach ($hooks as $row) if ($row['hook'] === $text) return;
        $hooks[] = ['hook' => $text, 'hook_type' => $type] + hook_score($type, $text);
    };

    if ($kind === 'beverage') {
        $add('problem', '생수 떨어진 날마다 편의점 들르는 거, 생각보다 진짜 귀찮음.');
        $add('benefit', ($spec !== '' ? $spec . '. ' : '') . '생수는 이런 구성이 집에 쟁여두기 제일 편함.');
        $add('curiosity', '생수는 물맛보다 먼저 봐야 하는 게 따로 있더라.');
        if (manmo_contains($p['name'], ['무라벨'])) $add('benefit', '무라벨 생수 한 번 쓰기 시작하면 빈 병 버릴 때 차이가 바로 느껴짐.');
        $add('target', '생수 맨날 떨어지고 나서 사는 사람은 이 구성 한번 봐봐.');
        $add('surprise', '매일 마시는 물인데 은근 돈보다 귀찮음이 더 큰 품목이 생수더라.');
    } elseif ($kind === 'food') {
        $add('problem', '배고플 때마다 뭘 먹을지 고민하는 시간이 제일 아까움.');
        $add('target', '냉장고 열었는데 먹을 거 없는 날 자주 오는 사람은 이런 거 하나 있으면 편함.');
        $add('benefit', ($spec !== '' ? $spec . '. ' : '') . '먹는 건 결국 끝까지 먹을 수 있는 구성이 제일 중요함.');
        $add('curiosity', '식품 살 때 가격보다 먼저 보는 기준이 하나 생겼어.');
    } elseif ($kind === 'household') {
        $add('problem', '생활용품은 꼭 다 떨어지고 나서야 생각남. 그때가 제일 귀찮음.');
        $add('benefit', ($spec !== '' ? $spec . '. ' : '') . '매일 쓰는 건 이런 묶음 구성이 생각보다 편함.');
        $add('target', '휴지나 세제 떨어질 때마다 급하게 사는 사람은 이런 타입이 잘 맞음.');
        $add('curiosity', '생활용품은 싸게 사는 것보다 더 중요한 게 있더라.');
    } elseif ($kind === 'electronics') {
        $add('surprise', $subject . ' 이건 스펙표보다 집에서 어떻게 쓰는지가 더 궁금해지는 물건임.');
        $add('benefit', '가전은 기능 하나보다 생활 동선 하나 줄여주는 게 훨씬 크게 체감됨.');
        $add('target', '자취방에서 자리만 차지하는 가전 싫은 사람은 이런 기준으로 보면 됨.');
        $add('curiosity', '가전 살 때 스펙보다 먼저 보는 게 하나 생겼어.');
    } else {
        $add('problem', $subject . ' 같은 건 필요할 때 없으면 유독 귀찮은 종류임.');
        $add('benefit', ($spec !== '' ? $spec . '. ' : '') . '구성이 딱 실사용 쪽으로 잡혀 있음.');
        $add('target', '자주 쓰는 물건 다시 사러 가는 거 귀찮은 사람은 이런 구성 잘 맞음.');
        $add('curiosity', '이런 제품은 가격보다 먼저 봐야 하는 게 따로 있음.');
    }

    $generic = [
        ['benefit', $subject . '은 화려한 설명보다 실제로 얼마나 자주 손이 가는지가 핵심임.'],
        ['problem', '사소한데 반복되면 은근 스트레스인 일이 있음. ' . $subject . '은 딱 그쪽을 줄여주는 타입.'],
        ['target', '자취하면서 “이거 또 사야 해?”가 자주 나오는 사람은 한번 볼 만함.'],
        ['curiosity', '처음엔 그냥 평범해 보였는데, 구성 뜯어보면 메리트가 보임.'],
        ['benefit', $benefit1 . '. 이게 생각보다 생활에서 크게 체감됨.'],
        ['benefit', $benefit2 . '. 결국 이런 게 오래 쓰게 됨.'],
        ['surprise', '비싸고 화려한 것보다 이런 실용형 제품이 오히려 손이 더 자주 감.'],
        ['problem', '돈보다 시간이 아까운 순간이 있는데, 이런 소모품에서 특히 그럼.'],
        ['target', '한 번 주문할 때 오래 신경 끄고 싶은 사람한테 잘 맞는 구성.'],
        ['curiosity', '후기 숫자보다 내가 먼저 보는 건 “이걸 왜 계속 사게 되나”임.'],
        ['benefit', ($spec !== '' ? $spec . '. ' : '') . '스펙 한 줄보다 실제 사용 장면이 바로 그려지는 구성임.'],
        ['problem', '매번 떨어지고, 매번 다시 사고. 이 반복이 싫으면 묶음 구성을 보게 됨.'],
        ['curiosity', '이 제품 메리트는 한눈에 보이는 데 있지 않음. 써야 하는 순간을 생각하면 바로 이해됨.'],
        ['target', '귀찮은 거 하나라도 줄이고 싶은 자취러는 이런 제품이 은근 만족도 높음.'],
        ['benefit', '필요할 때 바로 꺼내 쓸 수 있는 것. 결국 그게 제일 큰 장점임.'],
        ['surprise', '별거 아닌 것 같아도 매일 반복되는 불편 하나 없애면 체감이 꽤 큼.'],
        ['problem', '사놓고 안 쓰는 물건 말고, 진짜 생활에서 계속 쓰게 되는 게 필요했음.'],
        ['curiosity', $subject . '이 왜 많이 팔리는지 보니까 이유가 꽤 단순했음.'],
        ['target', '생활비 아끼는 것도 결국 “안 버리고 끝까지 쓰는 것”부터 시작이더라.'],
        ['benefit', '구성, 사용 빈도, 보관. 세 개가 맞으면 굳이 거창한 설명이 필요 없음.'],
    ];
    foreach ($generic as [$type, $text]) $add($type, $text);

    // Social proof is useful, but never invent it when Toss did not provide enough data.
    if ($p['proof'] !== '') $add('curiosity', $p['proof'] . '이라 뭐가 다른지 보게 됨.');
    if ($p['discount'] >= 40) $add('surprise', '지금은 할인폭도 꽤 크게 잡혀 있어서 더 눈에 들어오는 제품임.');

    usort($hooks, fn($a,$b) => $b['total'] <=> $a['total']);
    return array_slice($hooks, 0, 20);
}

function build_post_body(array $product, array $winner): string {
    $p = manmo_profile($product);
    $benefit1 = $p['benefits'][0];
    $benefit2 = $p['benefits'][1];
    $benefit3 = $p['benefits'][2];
    $spec = $p['specText'];

    $lines = [];
    $lines[] = $winner['hook'];
    $lines[] = '';

    if ($p['kind'] === 'beverage') {
        $lines[] = '생수는 특별한 기능이 있는 물건이 아니라서';
        $lines[] = '오히려 “얼마나 덜 귀찮게 살 수 있나”가 핵심임.';
    } elseif ($p['kind'] === 'food') {
        $lines[] = '먹는 건 결국 사놓고 끝까지 먹느냐가 제일 중요함.';
        $lines[] = '구성이 애매하면 냉장고 한쪽에서 그대로 잊히기 쉬움.';
    } elseif ($p['kind'] === 'household') {
        $lines[] = '생활용품은 매일 쓰는데 살 때만 존재감이 큼.';
        $lines[] = '한 번 잘 골라두면 다시 신경 쓸 일이 줄어드는 게 포인트.';
    } elseif ($p['kind'] === 'electronics') {
        $lines[] = '가전은 스펙 숫자보다 실제 생활에서 손이 얼마나 자주 가는지가 중요함.';
        $lines[] = '결국 동선 하나 줄여주는 기능이 제일 오래 남음.';
    } else {
        $lines[] = '이런 건 광고 문구보다 실제 쓰는 상황을 먼저 생각해보면 답이 빨리 나옴.';
        $lines[] = '자주 쓰는 물건일수록 귀찮음을 줄여주는지가 제일 중요함.';
    }

    $lines[] = '';
    if ($spec !== '') $lines[] = $spec . ' 구성.';
    $lines[] = $benefit1 . '.';
    $lines[] = $benefit3 . '.';

    if ($p['proof'] !== '') {
        $lines[] = '';
        $lines[] = $p['proof'] . '이라 실제 선택할 때 참고하기도 좋음.';
    }
    if ($p['discount'] >= 40) {
        $lines[] = '지금은 할인폭도 큰 편이라 조건 비교해볼 타이밍은 괜찮아 보임.';
    }

    $lines[] = '';
    $lines[] = '정확한 구성이나 현재 조건은 댓글에 남긴 링크에서 확인하면 됨.';

    return implode("\n", $lines);
}

function build_first_comment(array $product): string {
    $url = trim((string)($product['toss_share_url'] ?? '')) ?: '[쉐어링크 생성 전]';
    return "제품 확인 링크 👇\n{$url}\n\n"
        . "구성이나 가격은 바뀔 수 있으니 링크에서 현재 조건을 확인해줘.\n\n"
        . "※ 이 링크를 통해 구매가 발생하면 수수료를 제공받을 수 있어.";
}
