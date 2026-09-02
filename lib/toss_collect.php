<?php

declare(strict_types=1);
require_once __DIR__ . '/toss.php';

function toss_collect_products(string $source, int $targetCount, string $categoryId = '', string $startCursor = ''): array {
    $max = $source === 'today-deals' ? 30 : 100;
    $targetCount = max(1, min($max, $targetCount));
    $cursor = $startCursor;
    $items = [];
    $lastSuccess = ['items'=>[], 'hasNext'=>false, 'nextCursor'=>null];
    $category = null;
    $seenCursors = [];

    while (count($items) < $targetCount) {
        $query = ['size'=>1];
        if ($cursor !== '') $query['cursor'] = $cursor;

        if ($source === 'today-deals') {
            $page = toss_today_deals($query);
        } elseif ($source === 'category') {
            if ($categoryId === '') throw new InvalidArgumentException('categoryId required');
            $page = toss_category_best($categoryId, $query);
        } else {
            $page = toss_best_selling($query);
        }

        $success = is_array($page['success'] ?? null) ? $page['success'] : [];
        $pageItems = is_array($success['items'] ?? null) ? $success['items'] : [];
        foreach ($pageItems as $item) {
            if (!is_array($item)) continue;
            $items[] = $item;
            if (count($items) >= $targetCount) break;
        }
        if ($category === null && is_array($success['category'] ?? null)) $category = $success['category'];

        $lastSuccess = $success;
        $hasNext = (bool)($success['hasNext'] ?? false);
        $nextCursor = (string)($success['nextCursor'] ?? '');
        if (!$hasNext || $nextCursor === '') break;
        if (isset($seenCursors[$nextCursor])) break;
        $seenCursors[$nextCursor] = true;
        $cursor = $nextCursor;

        usleep(120000);
    }

    $out = [
        'items' => $items,
        'hasNext' => (bool)($lastSuccess['hasNext'] ?? false),
        'nextCursor' => $lastSuccess['nextCursor'] ?? null,
    ];
    if ($category !== null) $out['category'] = $category;
    return ['resultType'=>'SUCCESS', 'success'=>$out];
}
