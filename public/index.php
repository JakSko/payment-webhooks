<?php

declare(strict_types=1);

use App\Config;
use App\Http\WebhookController;
use App\JsonLogger;
use App\Security\SignatureVerifier;
use App\Webhook\EventStore;
use App\Webhook\PayloadValidator;

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

    if ($path === '/api/v1/webhooks/payment') {
        $controller = new WebhookController(
            new SignatureVerifier($config->webhookSecret),
            new PayloadValidator(),
            new EventStore($pdo),
            $logger,
        );
        // Read at most one byte over the limit, so an oversized body never fills memory.
        $body = (string) file_get_contents('php://input', false, null, 0, WebhookController::MAX_BODY_BYTES + 1);

        [$status, $response] = $controller->handle(
            $method,
            $_SERVER['CONTENT_TYPE'] ?? '',
            $_SERVER['HTTP_X_SIGNATURE'] ?? '',
            $body,
            $requestId,
            time(),
        );
        if ($status === 405) {
            header('Allow: POST');
        }
        http_response_code($status);
        echo json_encode($response + ['request_id' => $requestId], JSON_UNESCAPED_SLASHES);
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
