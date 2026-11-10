<?php declare(strict_types=1);

namespace App\UseCases\User;

use App\Enums\UserRoleEnum as Role;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Database\Capsule\Manager as DB;
use Oasys\Validation\Validator;
use Closure;

final class Update
{
  public function __construct(
    private UserPolicy $policy,
    private Validator $validatorPrototype
  ) {}

  public function __invoke(string $uuid, array $input, User $actor): User
  {
    $this->policy->assertCanUpdate($actor);

    return DB::transaction(function () use ($uuid, $input): User {
      $user = $this->loadUser($uuid);

      $this->validate($input, $user);
      $this->storeUser($user, $input);

      return $user;
    });
  }

  private function loadUser(string $uuid): User
  {
    return User::query()
      ->where('uuid', $uuid)
      ->lockForUpdate()
      ->first()
      ?? throw new NotFoundException('Uživatel nenalezen');
  }

  private function validate(array $input, User $user): void
  {
    $validator = (clone $this->validatorPrototype)
      ->allow('role')
      ->notEmpty('role')
      ->type('role', 'int')
      ->inEnum('role', Role::class);

    $user->isDraft() && $validator
      ->allow('email')
      ->type('email', 'string')
      ->maxLength('email', 128)
      ->format('email', 'email')
      ->custom(
        'email',
        Closure::fromCallable([$this, 'isEmailAvailable']),
        'Již použito',
        $user
      );

    $errors = $validator->validate($input);

    ! $errors
      || throw new ValidationException($errors);
  }

  private function isEmailAvailable(string $email, User $user): bool
  {
    return ! User::query()
      ->where('email_active', strtolower($email))
      ->where('id', '!=', $user->id)
      ->exists();
  }

  private function storeUser(User $user, array $input): void
  {
    $user
      ->fill($input)
      ->save();
  }
}
