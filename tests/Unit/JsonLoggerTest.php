<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\JsonLogger;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JsonLoggerTest extends TestCase
{
    public function testHidesPdoMessage(): void
    {
        $e = new PDOException('SQLSTATE[08006] connection to server at "db.internal" (10.0.0.5), user "app" failed');

        $described = JsonLogger::describe($e);

        $this->assertSame(PDOException::class, $described['exception']);
        $this->assertStringNotContainsString('db.internal', $described['error']);
        $this->assertStringNotContainsString('app', $described['error']);
    }

    public function testKeepsOwnExceptionMessage(): void
    {
        $described = JsonLogger::describe(new RuntimeException('Missing required environment variable WEBHOOK_SECRET'));

        $this->assertSame('Missing required environment variable WEBHOOK_SECRET', $described['error']);
    }
}
