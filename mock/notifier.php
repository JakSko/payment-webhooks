<?php

declare(strict_types=1);

// Mock of the external notification service (router script for `php -S`).
//   POST /notify          receive a notification, dedupe by Idempotency-Key
//   GET  /notifications   list received notifications (for tests)
//   POST /mode            {"mode": "up" | "down" | "reject" | "slow"} switch failure mode
//   GET  /health
// State lives in files under the system temp dir; the PHP built-in server handles one request at a time.

$modeFile = sys_get_temp_dir() . '/notifier-mode';
$storeFile = sys_get_temp_dir() . '/notifier-received.json';

$respond = static function (int $status, array $body): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
};
$received = static fn (): array => is_file($storeFile)
    ? (array) json_decode((string) file_get_contents($storeFile), true)
    : [];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$mode = is_file($modeFile) ? trim((string) file_get_contents($modeFile)) : 'up';

if ($method === 'GET' && $path === '/health') {
    $respond(200, ['status' => 'ok', 'mode' => $mode]);
    return;
}

if ($method === 'GET' && $path === '/notifications') {
    $respond(200, ['mode' => $mode, 'count' => count($received()), 'notifications' => array_values($received())]);
    return;
}

if ($method === 'POST' && $path === '/mode') {
    $requested = json_decode((string) file_get_contents('php://input'), true)['mode'] ?? null;
    if (!in_array($requested, ['up', 'down', 'reject', 'slow'], true)) {
        $respond(422, ['error' => 'mode must be one of: up, down, reject, slow']);
        return;
    }
    file_put_contents($modeFile, $requested);
    $respond(200, ['mode' => $requested]);
    return;
}

if ($method === 'POST' && $path === '/notify') {
    switch ($mode) {
        case 'down':
            $respond(503, ['error' => 'service unavailable (mock mode: down)']);
            return;
        case 'reject':
            $respond(400, ['error' => 'bad request (mock mode: reject)']);
            return;
        case 'slow':
            sleep(8); // longer than the worker's 5 s timeout
            break;
    }

    $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '';
    if ($key === '') {
        $respond(400, ['error' => 'missing Idempotency-Key']);
        return;
    }

    $all = $received();
    if (isset($all[$key])) {
        $respond(200, ['status' => 'duplicate']);
        return;
    }
    $all[$key] = json_decode((string) file_get_contents('php://input'), true);
    file_put_contents($storeFile, json_encode($all));
    error_log('notification received: ' . $key);
    $respond(200, ['status' => 'received']);
    return;
}

$respond(404, ['error' => 'not_found']);
