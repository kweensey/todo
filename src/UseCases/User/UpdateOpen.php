<?php declare(strict_types=1);

namespace App\UseCases\User;

use App\Enums\UserRoleEnum as Role;
use App\Exceptions\NotFoundException;
use App\Models\User;
use App\Policies\UserPolicy;

final class UpdateOpen
{
  public function __construct(
    private UserPolicy $policy
  ) {}

  public function __invoke(string $uuid, User $actor): array
  {
    $this->policy->assertCanUpdate($actor);

    $user = $this->loadUser($uuid);

    return $this->presentPage($actor, $user);
  }

  private function loadUser(string $uuid): User
  {
    return User::query()
      ->where('uuid', $uuid)
      ->first()
      ?? throw new NotFoundException('Uživatel nenalezen');
  }

  private function presentPage(User $actor, User $user): array
  {
    return [
      'actor' => $actor,
      'item'  => $user,
      'roles' => Role::cases()
    ];
  }
}
