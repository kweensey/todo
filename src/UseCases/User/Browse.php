<?php declare(strict_types=1);

namespace App\UseCases\User;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Collection;
use DateTimeImmutable;
use DateTimeInterface;

final class Browse
{
  public function __construct(
    private UserPolicy $policy
  ) {}

  public function __invoke(User $actor): array
  {
    $this->policy->assertCanBrowse($actor);

    $now   = new DateTimeImmutable();
    $users = $this->loadUsers($actor, $now);

    return $this->presentPage($actor, $users);
  }

  private function loadUsers(User $actor, DateTimeInterface $now): Collection
  {
    return User::query()
      ->orderByDesc('created_at')
      ->get()
      ->each(function (User $user) use ($actor, $now): void {
        $this->presentItem($user, $actor, $now);
      });
  }

  private function presentItem(User $user, User $actor, DateTimeInterface $now): void
  {
    $user->actions = [[
      'invite' => $this->policy->canInvite($actor)
        && $user->isInvitable($now),
      'edit' => $this->policy->canUpdate($actor)
    ], [
      'block' => $this->policy->canBlockTarget($actor, $user)
        && $user->isBlockable(),
      'unblock' => $this->policy->canUnblock($actor)
        && $user->isUnblockable(),
      'delete' => $this->policy->canDelete($actor)
        && $user->isDeletable(),
    ]];
  }

  private function presentPage(User $actor, Collection $items): array
  {
    return [
      'actor'   => $actor,
      'items'   => $items,
      'count'   => $items->count(),
      'actions' => $this->resolveActions($actor)
    ];
  }

  private function resolveActions(User $actor): array
  {
    return [
      'create' => $this->policy->canCreate($actor)
    ];
  }
}
