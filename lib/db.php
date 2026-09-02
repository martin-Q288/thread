<?php

declare(strict_types=1);
require_once __DIR__ . '/config.php';

function db_file(): string { return storage_path('db.json'); }

function db_default(): array {
    return [
        'products' => [],
        'posts' => [],
        'performance' => [],
        'benchmarks' => [],
        'winning_hooks' => [],
        'meta' => ['created_at' => date(DATE_ATOM)],
    ];
}

function db_read(): array {
    $file = db_file();
    if (!is_file($file)) {
        $db = db_default();
        db_write($db);
        return $db;
    }
    $raw = file_get_contents($file);
    $data = json_decode((string) $raw, true);
    return is_array($data) ? array_replace_recursive(db_default(), $data) : db_default();
}

function db_write(array $db): void {
    $file = db_file();
    $tmp = $file . '.tmp';
    file_put_contents($tmp, json_encode($db, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    rename($tmp, $file);
}

function db_insert(string $collection, array $row): array {
    $db = db_read();
    if (!isset($db[$collection]) || !is_array($db[$collection])) $db[$collection] = [];
    $last = end($db[$collection]);
    $id = is_array($last) ? ((int)($last['id'] ?? 0) + 1) : 1;
    $record = ['id' => $id] + $row;
    $db[$collection][] = $record;
    db_write($db);
    return $record;
}

function json_response($data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : $_POST;
}
