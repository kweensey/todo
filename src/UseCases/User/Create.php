<?php declare(strict_types=1);

namespace App\UseCases\User;

use App\Enums\UserRoleEnum as Role;
use App\Enums\UserStatusEnum as Status;
use App\Exceptions\ValidationException;
use App\Models\User;
use App\Policies\UserPolicy;
use Oasys\Validation\Validator;
use Closure;

final class Create
{
  public function __construct(
    private UserPolicy $policy,
    private Validator $validatorPrototype
  ) {}

  public function __invoke(array $input, User $actor): User
  {
    $this->policy->assertCanCreate($actor);
    $this->validate($input);

    return $this->storeUser($actor, $input);
  }

  private function validate(array $input): void
  {
    $errors = (clone $this->validatorPrototype)
      ->require('email', 'role')
      ->type('email', 'string')
      ->maxLength('email', 128)
      ->format('email', 'email')
      ->custom(
        'email',
        Closure::fromCallable([$this, 'isEmailAvailable']),
        'Již použito'
      )
      ->type('role', 'int')
      ->inEnum('role', Role::class)
      ->validate($input);

    ! $errors
      || throw new ValidationException($errors);
  }

  private function isEmailAvailable(string $email): bool
  {
    return ! User::query()
      ->where('email_active', strtolower($email))
      ->exists();
  }

  private function storeUser(User $actor, array $input): User
  {
    $user = new User($input);

    $user->created_by_id = $actor->id;
    $user->status        = Status::DRAFT;

    $user->save();

    return $user;
  }
}
