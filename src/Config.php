<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

/**
 * Configuration read once from the environment. A missing value fails fast
 * instead of falling back to an insecure default (e.g. an empty webhook secret).
 */
final class Config
{
    private function __construct(
        public readonly string $dbDsn,
        public readonly string $dbUser,
        public readonly string $dbPassword,
        public readonly string $webhookSecret,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            self::required('DB_DSN'),
            self::required('DB_USER'),
            self::required('DB_PASSWORD'),
            self::required('WEBHOOK_SECRET'),
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
}
