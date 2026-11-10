<?php declare(strict_types=1);

namespace App\UseCases\User\Recovery;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\RefreshToken;
use App\Models\User;
use App\UseCases\Auth\RevokeAll as RevokeRefreshTokens;
use App\Utilities\PasswordHasher;
use Illuminate\Database\Capsule\Manager as DB;
use Oasys\Validation\Validator;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;

final class Password
{
  public function __construct(
    private Validator $validatorPrototype,
    private PasswordHasher $hasher,
    private RevokeRefreshTokens $revokeRefreshTokens
  ) {}

  public function __invoke(string $uuid, array $input): User
  {
    $this->validate($input);

    return DB::transaction(function () use ($uuid, $input): User {
      $user = $this->loadUser($uuid, $input['token']);
      $now  = new DateTimeImmutable();

      $user->assertRecoveryNotExpired($now);
      $this->storePassword($user, $input['password']);

      ($this->revokeRefreshTokens)($user, $now);

      return $user;
    });
  }

  private function validate(array $input): void
  {
    $errors = (clone $this->validatorPrototype)
      ->require('token', 'password', 'password_check')
      ->type('token', 'string')
      ->format('token', 'hexadecimal')
      ->length('token', 32)
      ->type('password', 'string')
      ->minLength('password', 15)
      ->maxLength('password', 128)
      ->type('password_check', 'string')
      ->equalToField('password', 'password_check')
      ->validate($input);

    ! $errors
      || throw new ValidationException($errors);
  }

  private function loadUser(string $uuid, string $token): User
  {
    return User::query()
      ->whereRecovery($uuid, $token)
      ->lockForUpdate()
      ->first()
      ?? throw new NotFoundException('Uživatel nenalezen');
  }

  private function storePassword(User $user, string $password): void
  {
    $hash = $this->hasher->hash($password);

    $user
      ->confirmRecovery($hash)
      ->save();
  }
}
