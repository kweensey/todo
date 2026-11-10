<?php declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use App\Models\RefreshToken;
use App\Models\User;
use App\UseCases\Auth\RevokeFamily;
use App\Utilities\HmacHasher;
use Illuminate\Database\Capsule\Manager as DB;
use Oasys\Validation\Validator;
use DateTimeImmutable;
use DateTimeInterface;

final class Revoke
{
  public function __construct(
    private HmacHasher $hasher,
    private Validator $validatorPrototype,
    private RevokeFamily $revokeFamily
  ) {}

  public function __invoke(array $input): void
  {
    $this->validate($input);

    [$tokenId, $secret] = $this->parseToken($input['refresh_token']);

    DB::transaction(function () use ($tokenId, $secret): void {
      $token = $this->loadToken($tokenId);

      $this->validateSecret($token, $secret);

      $user  = $this->lockUser($token);
      $token = $this->lockToken($tokenId);
      $now   = new DateTimeImmutable();

      $this->validateSecret($token, $secret);
      $token->assertOwnedBy($user);

      ($this->revokeFamily)($token->family_id, $now);
    });
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

  private function lockUser(RefreshToken $token): User
  {
    return User::query()
      ->withTrashed()
      ->where('id', $token->user_id)
      ->lockForUpdate()
      ->first()
      ?? throw new UnauthorizedException('Invalid token');
  }

  private function validateSecret(RefreshToken $token, string $secret): void
  {
    $this->hasher->verify($secret, $token->token_hash)
      || throw new UnauthorizedException('Invalid token');
  }
}
