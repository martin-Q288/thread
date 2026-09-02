<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/hooks.php';
require_once dirname(__DIR__) . '/lib/threads.php';

if (PHP_SAPI !== 'cli') {
    require_admin();
}

$lockFile = storage_path('hourly.lock');
$lock = fopen($lockFile, 'c+');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "already running\n";
    exit;
}

try {
    $schedule = cfg()['schedule'];
    $hour = (int)date('G');
    if ($hour < $schedule['start_hour'] || $hour >= $schedule['end_hour']) {
        echo "outside posting window\n";
        exit;
    }

    $db = db_read();
    $today = date('Y-m-d');
    $todayPublished = array_values(array_filter($db['posts'], fn($p) => str_starts_with((string)($p['published_at'] ?? ''), $today)));
    if (count($todayPublished) >= $schedule['max_daily']) {
        echo "daily limit reached\n";
        exit;
    }

    $postIndex = null;
    foreach ($db['posts'] as $i => $p) {
        if (($p['status'] ?? '') === 'draft') { $postIndex = $i; break; }
    }

    if ($postIndex === null) {
        $usedProductIds = array_map(fn($p)=>(int)($p['product_id'] ?? 0), $todayPublished);
        $product = null;
        foreach ($db['products'] as $candidate) {
            if (!in_array((int)$candidate['id'], $usedProductIds, true) && trim((string)($candidate['toss_share_url'] ?? '')) !== '') {
                $product = $candidate;
                break;
            }
        }
        if (!$product) {
            echo "no publishable product with share link\n";
            exit;
        }

        $hooks = generate_hooks($product);
        $winner = $hooks[0];
        $newPost = [
            'id' => (int)((end($db['posts'])['id'] ?? 0) + 1),
            'product_id' => (int)$product['id'],
            'hook' => $winner['hook'],
            'hook_type' => $winner['hook_type'],
            'body' => build_post_body($product, $winner),
            'first_comment' => build_first_comment($product),
            'status' => 'draft',
            'created_at' => date(DATE_ATOM),
        ];
        $db['posts'][] = $newPost;
        db_write($db);
        $postIndex = count($db['posts']) - 1;
    }

    $post = $db['posts'][$postIndex];
    $result = threads_publish_with_comment((string)$post['body'], (string)$post['first_comment'], 15);
    $db = db_read();
    foreach ($db['posts'] as $i => $row) {
        if ((int)$row['id'] === (int)$post['id']) {
            $db['posts'][$i]['status'] = 'published';
            $db['posts'][$i]['thread_id'] = $result['thread']['id'] ?? null;
            $db['posts'][$i]['reply_id'] = $result['reply']['id'] ?? null;
            $db['posts'][$i]['published_at'] = date(DATE_ATOM);
            break;
        }
    }
    db_write($db);
    echo "published post #{$post['id']}\n";
} catch (Throwable $e) {
    file_put_contents(storage_path('cron_errors.log'), '[' . date(DATE_ATOM) . '] ' . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
