<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

/**
 * Configuration read once from the environment. A missing required value fails fast
 * instead of falling back to an insecure default (e.g. an empty webhook secret).
 * Only non-sensitive tuning values (retry schedule) have defaults.
 */
final class Config
{
    private function __construct(
        public readonly string $dbDsn,
        public readonly string $dbUser,
        public readonly string $dbPassword,
        public readonly string $webhookSecret,
        public readonly string $notifierUrl,
        public readonly int $retryMaxAttempts,
        public readonly int $retryBaseSeconds,
        public readonly int $retryCapSeconds,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            self::required('DB_DSN'),
            self::required('DB_USER'),
            self::required('DB_PASSWORD'),
            self::required('WEBHOOK_SECRET'),
            self::required('NOTIFIER_URL'),
            self::positiveInt('RETRY_MAX_ATTEMPTS', 6),
            self::positiveInt('RETRY_BASE_SECONDS', 2),
            self::positiveInt('RETRY_CAP_SECONDS', 300),
        );
    }

    public function createPdo(): PDO
    {
        return new PDO($this->dbDsn, $this->dbUser, $this->dbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private static function required(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            throw new RuntimeException(sprintf('Missing required environment variable %s', $name));
        }

        return $value;
    }

    private static function positiveInt(string $name, int $default): int
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return $default;
        }
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new RuntimeException(sprintf('Environment variable %s must be a positive integer', $name));
        }

        return (int) $value;
    }
}
