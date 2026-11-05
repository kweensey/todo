<?php declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Utilities\HmacHasher;
use App\Utilities\TokenGenerator;
use Firebase\JWT\JWT;
use Illuminate\Database\Capsule\Manager as DB;
use Oasys\Config\ArrayConfig as Config;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

final class TokenIssuer
{
  private const ACCESS_LIFESPAN  = 'PT15M';
  private const REFRESH_LIFESPAN = 'P30D';

  public function __construct(
    private Config $config,
    private TokenGenerator $generator,
    private HmacHasher $hasher
  ) {}

  public function issue(User $user, ?RefreshToken $oldToken = null): array
  {
    $privateKey = file_get_contents(APP_DIR . '/cert/private.key');

    if ($privateKey === false) {
      throw new RuntimeException('JWT private key unavailable');
    }

    $now       = new DateTimeImmutable();
    $lifespan  = new DateInterval(self::ACCESS_LIFESPAN);
    $expiresAt = $now->add($lifespan);

    $jwt = [
      'iss' => $this->config->get('app.host'),
      'aud' => $this->config->get('app.appId'),
      'sub' => $user->uuid,
      'iat' => $now->getTimestamp(),
      'exp' => $expiresAt->getTimestamp(),
      'jti' => $this->generator->generate()
    ];

    $accessToken = JWT::encode($jwt, $privateKey, 'RS256');

    [$refreshToken, $secret] = DB::transaction(function () use ($user, $jwt, $now, $oldToken): array {
      [$refreshToken, $secret] = $this->storeRefreshToken($user, $jwt['jti'], $now, $oldToken);

      $oldToken && $oldToken
        ->rotateTo($refreshToken)
        ->markUsed($now)
        ->save();

      return [$refreshToken, $secret];
    });

    return [
      'access_token'  => $accessToken,
      'refresh_token' => $refreshToken->token_id . '.' . $secret,
      'token_type'    => 'Bearer'
    ];
  }

  private function generateRefreshTokenSecret(): string
  {
    $bytes = random_bytes(32);

    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
  }

  private function storeRefreshToken(User $user, string $jti, DateTimeInterface $now, ?RefreshToken $oldToken = null): array
  {
    $secret = $this->generateRefreshTokenSecret();

    $familyId = $oldToken
      ? $oldToken->family_id
      : $this->generator->generate();

    $lifespan  = new DateInterval(self::REFRESH_LIFESPAN);

    $expiresAt = DateTimeImmutable::createFromInterface($now)
      ->add($lifespan);

    $token = new RefreshToken();

    $token->token_id   = $this->generator->generate();
    $token->token_hash = $this->hasher->hash($secret);
    $token->user_id    = $user->id;
    $token->family_id  = $familyId;
    $token->access_jti = $jti;
    $token->issued_at  = $now;
    $token->expires_at = $expiresAt;

    $token->save();

    return [$token, $secret];
  }
}
