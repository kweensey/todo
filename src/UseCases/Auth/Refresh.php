<?php declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use App\UseCases\Auth\RevokeFamily;
use App\Utilities\HmacHasher;
use Illuminate\Database\Capsule\Manager as DB;
use Oasys\Validation\Validator;
use Psr\Log\LoggerInterface;
use DateTimeImmutable;
use DateTimeInterface;

final class Refresh
{
  public function __construct(
    private TokenIssuer $tokenIssuer,
    private HmacHasher $hasher,
    private Validator $validatorPrototype,
    private LoggerInterface $logger,
    private RevokeFamily $revokeFamily
  ) {}

  public function __invoke(array $input): array
  {
    $this->validate($input);

    [$tokenId, $secret] = $this->parseToken($input['refresh_token']);

    $result = DB::transaction(function () use ($tokenId, $secret): array|User {
      $token = $this->loadToken($tokenId);

      $this->validateSecret($token, $secret);

      $user  = $this->loadUser($token);
      $token = $this->lockToken($tokenId);
      $now   = new DateTimeImmutable();

      $this->validateSecret($token, $secret);
      $token->assertOwnedBy($user);

      if ($token->isUsed()) {
        ($this->revokeFamily)($token->family_id, $now);

        return $user;
      }

      $this->validateToken($token, $now);

      return $this->tokenIssuer->issue($user, $token);
    });

    if ($result instanceof User) {
      $this->logger->warning('Refresh-token replay', [
        'user_uuid' => $result->uuid
      ]);

      throw new UnauthorizedException('Invalid token');
    }

    return $result;
  }

  private function validate(array $input): void
  {
    $errors = (clone $this->validatorPrototype)
      ->require('refresh_token')
      ->allow('refresh_token')
      ->type('refresh_token', 'string')
      ->regex('refresh_token', '/\A[a-fA-F0-9]{32}\.[A-Za-z0-9_-]{43}\z/')
      ->validate($input);

    ! $errors
      || throw new ValidationException($errors);
  }

  private function parseToken(string $token): array
  {
    return explode('.', $token, 2);
  }

  private function loadToken(string $tokenId): RefreshToken
  {
    return RefreshToken::query()
      ->where('token_id', $tokenId)
      ->first()
      ?? throw new UnauthorizedException('Invalid token');
  }

  private function lockToken(string $tokenId): RefreshToken
  {
    return RefreshToken::query()
      ->where('token_id', $tokenId)
      ->lockForUpdate()
      ->first()
      ?? throw new UnauthorizedException('Invalid token');
  }

  private function loadUser(RefreshToken $token): User
  {
    return User::query()
      ->where('id', $token->user_id)
      ->registered()
      ->lockForUpdate()
      ->first()
      ?? throw new UnauthorizedException('Invalid token');
  }

  private function validateSecret(RefreshToken $token, string $secret): void
  {
    $this->hasher->verify($secret, $token->token_hash)
      || throw new UnauthorizedException('Invalid token');
  }

  private function validateToken(RefreshToken $token, DateTimeInterface $now): void
  {
    ! $token->isExpired($now)
      && ! $token->isRevoked()
      || throw new UnauthorizedException('Invalid token');
  }
}
