<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Notification\RetryPolicy;
use App\Notification\SendResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    private static function policy(?\Closure $random = null): RetryPolicy
    {
        return new RetryPolicy(maxAttempts: 6, baseSeconds: 2, capSeconds: 300, random: $random ?? static fn (int $max): int => 0);
    }

    /** @return iterable<string, array{SendResult, bool}> */
    public static function results(): iterable
    {
        yield 'timeout / connection error' => [new SendResult(null, 'timeout'), true];
        yield '500' => [new SendResult(500), true];
        yield '503' => [new SendResult(503), true];
        yield '408 request timeout' => [new SendResult(408), true];
        yield '429 rate limited' => [new SendResult(429), true];
        yield '400 permanent' => [new SendResult(400), false];
        yield '401 permanent' => [new SendResult(401), false];
        yield '404 permanent' => [new SendResult(404), false];
        yield '422 permanent' => [new SendResult(422), false];
    }

    #[DataProvider('results')]
    public function testClassifiesFailures(SendResult $result, bool $retryable): void
    {
        $this->assertSame($retryable, self::policy()->shouldRetry($result, 1));
    }

    public function testSuccessIsNotRetried(): void
    {
        $this->assertTrue((new SendResult(200))->isSuccess());
        $this->assertTrue((new SendResult(204))->isSuccess());
        $this->assertFalse((new SendResult(302))->isSuccess());
        $this->assertFalse((new SendResult(null, 'timeout'))->isSuccess());
    }

    public function testStopsRetryingAtMaxAttempts(): void
    {
        $policy = self::policy();

        $this->assertTrue($policy->shouldRetry(new SendResult(503), 5));
        $this->assertFalse($policy->shouldRetry(new SendResult(503), 6));
    }

    public function testDelayGrowsExponentiallyUpToCap(): void
    {
        $policy = self::policy();

        $delays = array_map(static fn (int $attempt): int => $policy->delaySeconds($attempt), range(1, 10));

        $this->assertSame([2, 4, 8, 16, 32, 64, 128, 256, 300, 300], $delays);
    }

    public function testJitterAddsAtMostTwentyPercent(): void
    {
        $maxJitter = self::policy(static fn (int $max): int => $max);

        $this->assertSame(19, $maxJitter->delaySeconds(4));   // 16 + floor(16 * 0.2)
        $this->assertSame(360, $maxJitter->delaySeconds(20)); // cap 300 + 60
    }
}
