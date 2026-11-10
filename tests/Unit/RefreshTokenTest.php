<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\DomainStateException;
use App\Exceptions\UnauthorizedException;
use App\Models\RefreshToken;
use App\Models\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RefreshTokenTest extends TestCase
{
    private function token(): RefreshToken
    {
        $token = new RefreshToken();
        $token->setDateFormat('Y-m-d H:i:s');
        return $token;
    }

    public function testExpirationAndUsedFlagsAtTheExactBoundary(): void
    {
        $token = $this->token();
        $token->expires_at = new DateTimeImmutable('2030-01-01 12:00:00');
        self::assertFalse($token->isUsed());
        self::assertFalse($token->isExpired(new DateTimeImmutable('2030-01-01 11:59:59')));
        self::assertTrue($token->isExpired(new DateTimeImmutable('2030-01-01 12:00:00')));

        $token->markUsed(new DateTimeImmutable('2030-01-01 11:00:00'));
        self::assertTrue($token->isUsed());
        self::assertFalse($token->isRevoked());

        $token->revoked_at = new DateTimeImmutable('2030-01-01 11:30:00');
        self::assertTrue($token->isRevoked());
    }

    public function testRotationOnlyWithinSameUserAndFamily(): void
    {
        $old = $this->token();
        $old->user_id = 'user-1';
        $old->family_id = 'family-1';
        $replacement = $this->token();
        $replacement->user_id = 'user-1';
        $replacement->family_id = 'family-1';
        $replacement->token_id = 'next-token';

        self::assertSame($old, $old->rotateTo($replacement));
        self::assertSame('next-token', $old->rotated_to_token_id);
    }

    public function testCannotRotateAcrossDifferentTokenFamilies(): void
    {
        $old = $this->token();
        $old->user_id = 'owner';
        $old->family_id = 'one';
        $new = $this->token();
        $new->user_id = 'owner';
        $new->family_id = 'two';

        $this->expectException(DomainStateException::class);
        $old->rotateTo($new);
    }

    public function testCannotRotateTokenToAnotherUser(): void
    {
        $old = $this->token();
        $old->user_id = 'owner';
        $old->family_id = 'family';
        $new = $this->token();
        $new->user_id = 'stranger';
        $new->family_id = 'family';

        $this->expectException(DomainStateException::class);
        $old->rotateTo($new);
    }

    public function testDifferentUserCannotClaimRefreshToken(): void
    {
        $token = $this->token();
        $token->user_id = 'owner';
        $user = new User();
        $user->id = '00000000-0000-4000-8000-000000000010';

        $this->expectException(UnauthorizedException::class);
        $token->assertOwnedBy($user);
    }
}
