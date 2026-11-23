<?php

declare(strict_types=1);

namespace App;

/**
 * One JSON line per entry on stderr, so `docker compose logs` shows it.
 * Callers pass identifiers only (request_id, event_id, order_id), never payload or personal data.
 */
final class JsonLogger
{
    public function __construct(private readonly string $stream = 'php://stderr')
    {
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /**
     * Data-free description of an exception: PDO messages may contain the host, user or query values,
     * so for them only the SQLSTATE is kept. Messages of our own exceptions are safe and kept.
     * @return array{exception: string, error: string}
     */
    public static function describe(\Throwable $e): array
    {
        return [
            'exception' => $e::class,
            'error' => $e instanceof \PDOException ? 'SQLSTATE ' . $e->getCode() : $e->getMessage(),
        ];
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        $entry = ['ts' => gmdate('Y-m-d\TH:i:s\Z'), 'level' => $level, 'msg' => $message] + $context;
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        file_put_contents($this->stream, $line . PHP_EOL, FILE_APPEND);
    }
}
