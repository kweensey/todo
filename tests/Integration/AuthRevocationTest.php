<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\UnauthorizedException;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use App\UseCases\Auth\Refresh;
use App\UseCases\Auth\Revoke;
use App\UseCases\Auth\RevokeAll;
use App\UseCases\Auth\RevokeFamily;
use App\Utilities\HmacHasher;
use App\Utilities\TokenGenerator;
use Oasys\Config\ArrayConfig as Config;
use Oasys\Validation\Validator;
use Psr\Log\NullLogger;
use Tests\Support\DatabaseTestCase;
use DateTimeImmutable;

final class AuthRevocationTest extends DatabaseTestCase
{
    private HmacHasher $hasher;
    private Config $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new Config(self::APP_DIR . '/config/app.php');
        $this->hasher = new HmacHasher($this->config);
    }

    public function testExplicitRevokeAffectsOnlyTheMatchingFamily(): void
    {
        $user = $this->createUser();
        $family = $this->id();
        $secret = $this->secret();

        $current = $this->token($user, $family, $secret);
        $sibling = $this->token($user, $family, $this->secret());
        $unrelated = $this->token($user, $this->id(), $this->secret());

        $revoke = new Revoke($this->hasher, new Validator(), new RevokeFamily());
        $revoke(['refresh_token' => $this->raw($current, $secret)]);

        self::assertNotNull($current->fresh()->revoked_at);
        self::assertNotNull($sibling->fresh()->revoked_at);
        self::assertNull($unrelated->fresh()->revoked_at);
    }

    public function testRevokeAllDoesNotAffectAnotherUser(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $one = $this->token($user, $this->id(), $this->secret());
        $two = $this->token($user, $this->id(), $this->secret());
        $untouched = $this->token($other, $this->id(), $this->secret());

        (new RevokeAll())($user, new DateTimeImmutable());

        self::assertNotNull($one->fresh()->revoked_at);
        self::assertNotNull($two->fresh()->revoked_at);
        self::assertNull($untouched->fresh()->revoked_at);
    }

    public function testReplayRevokesTheFamilyAndCommitsDespiteUnauthorizedResponse(): void
    {
        $user = $this->createUser();
        $family = $this->id();
        $oldSecret = $this->secret();

        $used = $this->token($user, $family, $oldSecret, new DateTimeImmutable('-1 minute'));
        $replacement = $this->token($user, $family, $this->secret());
        $independent = $this->token($user, $this->id(), $this->secret());
        $used->rotated_to_token_id = $replacement->token_id;
        $used->save();

        try {
            ($this->refresh())(['refresh_token' => $this->raw($used, $oldSecret)]);
            self::fail('Expected UnauthorizedException for a replayed token.');
        } catch (UnauthorizedException) {
            // continue
        }

        self::assertNotNull($used->fresh()->revoked_at);
        self::assertNotNull($replacement->fresh()->revoked_at);
        self::assertNull($independent->fresh()->revoked_at);
    }

    public function testInvalidSecretCannotRevokeAValidSession(): void
    {
        $user = $this->createUser();
        $secret = $this->secret();
        $token = $this->token($user, $this->id(), $secret);
        $wrongSecret = $this->secret();

        self::assertNotSame($secret, $wrongSecret);

        try {
            ($this->refresh())(['refresh_token' => $this->raw($token, $wrongSecret)]);
            self::fail('Expected UnauthorizedException for an incorrect secret.');
        } catch (UnauthorizedException) {
            // continue
        }

        self::assertNull($token->fresh()->revoked_at);
        self::assertNull($token->fresh()->used_at);
    }

    private function refresh(): Refresh
    {
        return new Refresh(
            new TokenIssuer($this->config, new TokenGenerator(), $this->hasher),
            $this->hasher,
            new Validator(),
            new NullLogger(),
            new RevokeFamily()
        );
    }

    private function token(
        User $user,
        string $family,
        string $secret,
        ?DateTimeImmutable $usedAt = null
    ): RefreshToken {
        $token = new RefreshToken();
        $token->token_id = $this->id();
        $token->access_jti = $this->id();
        $token->user_id = $user->getKey();
        $token->family_id = $family;
        $token->token_hash = $this->hasher->hash($secret);
        $token->issued_at = new DateTimeImmutable('-5 minutes');
        $token->expires_at = new DateTimeImmutable('+1 day');
        $token->used_at = $usedAt;
        $token->save();

        return $token;
    }

    private function raw(RefreshToken $token, string $secret): string
    {
        return $token->token_id . '.' . $secret;
    }

    private function id(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function secret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
