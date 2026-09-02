<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/threads.php';

try {
    header('Location: ' . threads_authorize_url(), true, 302);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Threads OAuth start error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
