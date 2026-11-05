<?php declare(strict_types=1);

namespace App\UseCases\User\Change;

use App\Exceptions\DomainStateException;
use App\Exceptions\ValidationException;
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

  public function __invoke(array $input, User $actor): User
  {
    $this->validate($input);

    return DB::transaction(function () use ($input, $actor): User {
      $user = $this->lockUser($actor);

      $this->validateCurrentPassword(
        $input['password_current'],
        $user
      );

      $this->storePassword($user, $input['password_new']);

      $now = new DateTimeImmutable();

      ($this->revokeRefreshTokens)($user, $now);

      return $user;
    });
  }

  private function validate(array $input): void
  {
    $errors = (clone $this->validatorPrototype)
      ->require('password_current', 'password_new', 'password_check')
      ->type('password_current', 'string')
      ->type('password_new', 'string')
      ->minLength('password_new', 15)
      ->maxLength('password_new', 128)
      ->type('password_check', 'string')
      ->equalToField('password_new', 'password_check')
      ->validate($input);

    ! $errors
      || throw new ValidationException($errors);
  }

  private function lockUser(User $actor): User
  {
    return User::query()
      ->whereKey($actor->getKey())
      ->registered()
      ->lockForUpdate()
      ->first()
      ?? throw new DomainStateException('Uživatele se nepodařilo uzamknout');
  }

  private function validateCurrentPassword(string $password, User $user): void
  {
    $errors = (clone $this->validatorPrototype)
      ->custom(
        'password_current',
        'password_verify',
        'Nesprávné',
        $user->password
      )
      ->validate(['password_current' => $password]);

    ! $errors
      || throw new ValidationException($errors);
  }

  private function storePassword(User $user, string $password): void
  {
    $hash = $this->hasher->hash($password);

    $user
      ->changePassword($hash)
      ->save();
  }
}
