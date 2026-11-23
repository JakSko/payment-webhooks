<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Security\InvalidSignature;
use App\Security\SignatureVerifier;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignatureVerifierTest extends TestCase
{
    private const SECRET = 'test-secret';
    private const NOW = 1_718_029_200;
    private const BODY = '{"event":"charge.successful"}';

    public function testAcceptsValidSignature(): void
    {
        $verifier = new SignatureVerifier(self::SECRET);

        $verifier->verify($verifier->sign(self::BODY, self::NOW), self::BODY, self::NOW);

        $this->addToAssertionCount(1);
    }

    public function testAcceptsTimestampInsideTolerance(): void
    {
        $verifier = new SignatureVerifier(self::SECRET, 300);

        $verifier->verify($verifier->sign(self::BODY, self::NOW - 300), self::BODY, self::NOW);

        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidHeaders(): iterable
    {
        $other = new SignatureVerifier('other-secret');
        $valid = (new SignatureVerifier(self::SECRET))->sign(self::BODY, self::NOW);

        yield 'missing header' => ['', 'missing'];
        yield 'garbage' => ['not-a-signature', 'malformed'];
        yield 'no signature part' => ['t=' . self::NOW, 'malformed'];
        yield 'non numeric timestamp' => ['t=abc,v1=' . str_repeat('a', 64), 'malformed'];
        yield 'wrong secret' => [$other->sign(self::BODY, self::NOW), 'mismatch'];
        yield 'tampered signature' => [substr($valid, 0, -1) . (str_ends_with($valid, '0') ? '1' : '0'), 'mismatch'];
        yield 'too old' => [(new SignatureVerifier(self::SECRET))->sign(self::BODY, self::NOW - 301), 'expired'];
        yield 'too far in future' => [(new SignatureVerifier(self::SECRET))->sign(self::BODY, self::NOW + 301), 'expired'];
    }

    #[DataProvider('invalidHeaders')]
    public function testRejectsInvalidSignature(string $header, string $expectedReason): void
    {
        $verifier = new SignatureVerifier(self::SECRET, 300);

        try {
            $verifier->verify($header, self::BODY, self::NOW);
            $this->fail('Expected InvalidSignature');
        } catch (InvalidSignature $e) {
            $this->assertSame($expectedReason, $e->reason);
        }
    }

    public function testRejectsModifiedBody(): void
    {
        $verifier = new SignatureVerifier(self::SECRET);
        $header = $verifier->sign(self::BODY, self::NOW);

        $this->expectException(InvalidSignature::class);
        $verifier->verify($header, self::BODY . ' ', self::NOW);
    }

    public function testRefusesEmptySecret(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SignatureVerifier('');
    }
}
