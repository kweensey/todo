<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\DomainStateException;
use App\Exceptions\TooManyAttemptsException;
use App\Models\Challenge;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ChallengeTest extends TestCase
{
    private function challenge(): Challenge
    {
        $challenge = new Challenge();
        $challenge->setDateFormat('Y-m-d H:i:s');
        $challenge->attempts = 0;
        $challenge->created_at = new DateTimeImmutable('2030-04-01 12:00:00');
        $challenge->expires_at = new DateTimeImmutable('2030-04-01 12:10:00');
        return $challenge;
    }

    public function testCodeExpiresAtBoundary(): void
    {
        $challenge = $this->challenge();
        self::assertFalse($challenge->isExpired(new DateTimeImmutable('2030-04-01 12:09:59')));
        self::assertTrue($challenge->isExpired(new DateTimeImmutable('2030-04-01 12:10:00')));
        $this->expectException(DomainStateException::class);
        $challenge->assertNotExpired(new DateTimeImmutable('2030-04-01 12:10:00'));
    }

    public function testFiveFailedAttemptsExhaustCode(): void
    {
        $challenge = $this->challenge();
        for ($i = 0; $i < 5; $i++) {
            self::assertSame($challenge, $challenge->failAttempt());
        }
        self::assertFalse($challenge->hasAttemptsRemaining());
        $this->expectException(TooManyAttemptsException::class);
        $challenge->assertAttemptsRemaining();
    }

    public function testCannotResendBeforeCooldownElapses(): void
    {
        $challenge = $this->challenge();
        self::assertFalse($challenge->isResendable(new DateTimeImmutable('2030-04-01 12:00:59')));
        self::assertTrue($challenge->isResendable(new DateTimeImmutable('2030-04-01 12:01:00')));
    }
}
