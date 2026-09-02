<?php

declare(strict_types=1);

function hook_templates(): array {
    return [
        'empathy' => [
            '다이어트 시작하면 식비가 더 드는 게 늘 이상했어.',
            '혼자 살수록 많이 사는 게 꼭 절약은 아니더라.',
            '살은 빼고 싶은데 식비까지 늘어나는 건 좀 억울하더라.',
        ],
        'regret' => [
            '이걸 왜 자취 7년차에 알았나 싶었어.',
            '진작 한 끼 가격부터 계산했으면 덜 버렸을 것 같아.',
        ],
        'number' => [
            '배달 한 번 값으로 몇 끼를 해결할 수 있는지부터 보게 됐어.',
            '한 끼 5천 원 안쪽이면 생각보다 선택지가 꽤 있더라.',
        ],
        'confession' => [
            '나 진짜 배달앱 못 끊는 사람이었어.',
            '맛없는 다이어트 음식은 결국 냉동실에서 버리게 되더라.',
        ],
        'surprise' => [
            '다이어트 시작하고 오히려 식비가 줄어든 이유가 있었어.',
            '비싼 다이어트 식단보다 오래 가는 건 따로 있더라.',
        ],
        'target' => [
            '혼자 사는데 식비가 자꾸 새는 사람은 이 기준 한번 봐봐.',
            '퇴근하고 배달부터 켜는 사람이라면 냉동실 구성이 진짜 중요해.',
        ],
        'curiosity' => [
            '맛있게 먹고 돈도 아끼고 싶어서 기준을 세 개만 남겼어.',
            '요즘은 다이어트 음식인지보다 먼저 보는 게 따로 있어.',
        ],
    ];
}

function hook_score(string $type, string $hook): array {
    $stop = in_array($type, ['empathy','surprise','target','curiosity'], true) ? 9 : 8;
    $curiosity = in_array($type, ['surprise','curiosity','regret'], true) ? 9 : 8;
    $human = 9;
    $comment = in_array($type, ['empathy','target','curiosity'], true) ? 9 : 8;
    $purchase = in_array($type, ['empathy','number','target'], true) ? 8 : 7;
    $total = $stop*.30 + $curiosity*.25 + $human*.20 + $comment*.15 + $purchase*.10;
    return compact('stop','curiosity','human','comment','purchase') + ['total' => round($total, 2)];
}

function generate_hooks(array $product): array {
    $out = [];
    foreach (hook_templates() as $type => $templates) {
        foreach ($templates as $hook) {
            $out[] = ['hook' => $hook, 'hook_type' => $type] + hook_score($type, $hook);
        }
    }
    usort($out, fn($a,$b) => $b['total'] <=> $a['total']);
    return array_slice($out, 0, 20);
}

function build_post_body(array $product, array $winner): string {
    $feature = trim((string)($product['description'] ?? ''));
    $price = (int)($product['price'] ?? 0);
    $category = (string)($product['category'] ?? '');

    $middle = $feature !== ''
        ? "그래서 요즘은 제품 이름보다\n{$feature} 같은 실용적인 조건을 먼저 봐."
        : "그래서 요즘은 맛, 한 끼 가격, 보관 편의성처럼\n실제로 오래 유지할 수 있는 조건부터 봐.";

    $priceLine = $price > 0
        ? "가격은 " . number_format($price) . "원대라서\n내 기준에선 한 번 비교해볼 만했어."
        : "가격까지 비교해보니\n자취하면서 챙겨두기 괜찮은 편이었어.";

    return $winner['hook'] . "\n\n"
        . "샐러드 하나 시켜도 만 원이 훌쩍 넘고,\n"
        . "관리한다고 이것저것 사면 오히려 카드값이 늘잖아.\n\n"
        . $middle . "\n\n"
        . $priceLine . "\n\n"
        . "맛없는 걸 참고 먹으면서 돈까지 더 쓰는 방식은\n"
        . "나는 오래 못 하겠더라.\n\n"
        . "내가 확인한 제품은 댓글에 남겨둘게.";
}

function build_first_comment(array $product): string {
    $url = trim((string)($product['toss_share_url'] ?? '')) ?: '[쉐어링크 생성 전]';
    return "내가 확인한 제품은 여기야 👇\n{$url}\n\n"
        . "가격이나 구성은 바뀔 수 있으니 들어가서 확인하는 게 정확해.\n\n"
        . "※ 이 링크를 통해 구매가 발생하면 수수료를 제공받을 수 있어.";
}
