<?php declare(strict_types=1);

namespace App\UseCases\User;

use App\Exceptions\NotFoundException;
use App\Models\User;
use App\Policies\UserPolicy;
use App\UseCases\Auth\RevokeAll as RevokeRefreshTokens;
use Illuminate\Database\Capsule\Manager as DB;
use DateTimeImmutable;

final class Block
{
  public function __construct(
    private UserPolicy $policy,
    private RevokeRefreshTokens $revokeRefreshTokens
  ) {}

  public function __invoke(string $uuid, User $actor): User
  {
    $this->policy->assertCanBlock($actor);

    return DB::transaction(function () use ($uuid, $actor): User {
      $user = $this->loadUser($uuid);
      $now  = new DateTimeImmutable();

      $this->policy->assertCanBlockTarget($actor, $user);
      $user->assertBlockable();
      $this->storeBlock($user);
      
      ($this->revokeRefreshTokens)($user, $now);

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

  private function storeBlock(User $user): void
  {
    $user
      ->block()
      ->save();
  }
}
