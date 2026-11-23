<?php

declare(strict_types=1);

use App\Config;
use App\JsonLogger;

require __DIR__ . '/../vendor/autoload.php';

$logger = new JsonLogger();
$requestId = bin2hex(random_bytes(8));
header('Content-Type: application/json');
header('X-Request-Id: ' . $requestId);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

try {
    $config = Config::fromEnv();
    $pdo = $config->createPdo();

    if ($path === '/health' && $method === 'GET') {
        $pdo->query('SELECT 1');
        echo json_encode(['status' => 'ok']);
        return;
    }

    http_response_code(404);
    echo json_encode(['error' => 'not_found', 'request_id' => $requestId]);
} catch (Throwable $e) {
    $logger->error('unhandled exception', [
        'request_id' => $requestId,
        'exception' => $e::class,
        'error' => $e->getMessage(),
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'internal_error', 'request_id' => $requestId]);
}
