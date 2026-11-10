<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UserStatusEnum;
use App\Models\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserSecurityRulesTest extends TestCase
{
    private function user(): User
    {
        $user = new User();
        $user->setDateFormat('Y-m-d H:i:s');
        return $user;
    }

    public function testFiveFailedLoginsTriggerFiveMinuteCooldownAndSuccessClearsIt(): void
    {
        $user = $this->user();
        $user->failed_login_count = 0;
        $now = new DateTimeImmutable('2030-04-01 12:00:00');

        for ($i = 0; $i < 5; $i++) {
            $user->recordFailedLogin($now);
        }

        self::assertTrue($user->hasReachedLoginAttemptsLimit());
        self::assertFalse($user->canRequestLogin(new DateTimeImmutable('2030-04-01 12:04:59')));
        self::assertTrue($user->canRequestLogin(new DateTimeImmutable('2030-04-01 12:05:00')));

        $user->recordLogin(new DateTimeImmutable('2030-04-01 12:05:00'));
        self::assertSame(0, $user->failed_login_count);
        self::assertNull($user->failed_login_at);
        self::assertTrue($user->canRequestLogin(new DateTimeImmutable('2030-04-01 12:05:01')));
    }

    public function testRecoveryResendCooldownAndExpiration(): void
    {
        $user = $this->user();
        $user->recovery_issued_count = 0;
        $user->recovery_requested_at = new DateTimeImmutable('2030-04-01 12:00:00');

        self::assertFalse($user->canRequestRecovery(new DateTimeImmutable('2030-04-01 12:00:59')));
        self::assertTrue($user->canRequestRecovery(new DateTimeImmutable('2030-04-01 12:01:00')));
        self::assertFalse($user->isRecoveryExpired(new DateTimeImmutable('2030-04-01 12:59:59')));
        self::assertTrue($user->isRecoveryExpired(new DateTimeImmutable('2030-04-01 13:00:00')));
    }

    public function testInviteResendAndExpiryBoundaries(): void
    {
        $user = $this->user();
        $now = new DateTimeImmutable('2030-04-01 12:00:00');
        $user->status = UserStatusEnum::DRAFT;
        self::assertTrue($user->isInvitable($now));

        $user->status = UserStatusEnum::INVITE;
        $user->invite_date = $now;
        self::assertFalse($user->isInvitable(new DateTimeImmutable('2030-04-01 12:29:59')));
        self::assertTrue($user->isInvitable(new DateTimeImmutable('2030-04-01 12:30:00')));
        self::assertFalse($user->isInviteExpired(new DateTimeImmutable('2030-04-15 11:59:59')));
        self::assertTrue($user->isInviteExpired(new DateTimeImmutable('2030-04-15 12:00:00')));
    }

    public function testOtpLimitsResetWhenWindowExpires(): void
    {
        $user = $this->user();
        $user->otp_failed_attempts = 0;
        $user->otp_issued_count = 0;
        $now = new DateTimeImmutable('2030-04-01 12:00:00');

        for ($i = 0; $i < 20; $i++) {
            $user->failOtpAttempt($now);
        }
        self::assertFalse($user->canVerifyOtp(new DateTimeImmutable('2030-04-01 12:59:59')));
        self::assertTrue($user->canVerifyOtp(new DateTimeImmutable('2030-04-01 13:00:00')));

        for ($i = 0; $i < 10; $i++) {
            $user->recordOtpIssued($now);
        }
        self::assertFalse($user->canIssueOtp(new DateTimeImmutable('2030-04-02 11:59:59')));
        self::assertTrue($user->canIssueOtp(new DateTimeImmutable('2030-04-02 12:00:00')));
    }
}
