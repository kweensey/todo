<?php declare(strict_types=1);

namespace App\UseCases\User;

use App\Enums\UserRoleEnum as Role;
use App\Models\User;
use App\Policies\UserPolicy;

final class CreateOpen
{
  public function __construct(
    private UserPolicy $policy
  ) {}

  public function __invoke(User $actor): array
  {
    $this->policy->assertCanCreate($actor);

    return $this->presentPage($actor);
  }

  private function presentPage(User $actor): array
  {
    return [
      'actor' => $actor,
      'roles' => Role::cases()
    ];
  }
}
