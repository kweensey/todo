<?php declare(strict_types=1);

namespace App\UseCases\User;

use App\Exceptions\NotFoundException;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Database\Capsule\Manager as DB;

final class Unblock
{
  public function __construct(
    private UserPolicy $policy
  ) {}

  public function __invoke(string $uuid, User $actor): User
  {
    $this->policy->assertCanUnblock($actor);

    return DB::transaction(function () use ($uuid): User {
      $user = $this->loadUser($uuid);

      $user->assertUnblockable();
      $this->storeUnblock($user);

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

  private function storeUnblock(User $user): void
  {
    $user
      ->unblock()
      ->save();
  }
}
