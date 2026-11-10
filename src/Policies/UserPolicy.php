<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Exceptions\ForbiddenException;

final class UserPolicy
{
  public function canBrowse(User $actor): bool
  {
    return $actor->isAdmin();
  }

  public function assertCanBrowse(User $actor): void
  {
    $this->canBrowse($actor)
      || throw new ForbiddenException('Nemůžete procházet uživatele');
  }

  public function canCreate(User $actor): bool
  {
    return $actor->isAdmin();
  }

  public function assertCanCreate(User $actor): void
  {
    $this->canCreate($actor)
      || throw new ForbiddenException('Nemůžete vytvořit uživatele');
  }

  public function canUpdate(User $actor): bool
  {
    return $actor->isAdmin();
  }

  public function assertCanUpdate(User $actor): void
  {
    $this->canUpdate($actor)
      || throw new ForbiddenException('Nemůžete upravit uživatele');
  }

  public function canInvite(User $actor): bool
  {
    return $actor->isAdmin();
  }

  public function assertCanInvite(User $actor): void
  {
    $this->canInvite($actor)
      || throw new ForbiddenException('Nemůžete pozvat uživatele');
  }

  public function canBlock(User $actor): bool
  {
    return $actor->isAdmin();
  }

  public function assertCanBlock(User $actor): void
  {
    $this->canBlock($actor)
      || throw new ForbiddenException('Nemůžete blokovat uživatele');
  }

  public function canUnblock(User $actor): bool
  {
    return $actor->isAdmin();
  }

  public function assertCanUnblock(User $actor): void
  {
    $this->canUnblock($actor)
      || throw new ForbiddenException('Nemůžete odblokovat uživatele');
  }

  public function canBlockTarget(User $actor, User $target): bool
  {
    return $actor->getKey() !== $target->getKey()
      && $actor->created_by_id !== $target->getKey();
  }

  public function assertCanBlockTarget(User $actor, User $target): void
  {
    $this->canBlockTarget($actor, $target)
      || throw new ForbiddenException('Nemůžete blokovat tohoto uživatele');
  }

  public function canDelete(User $actor): bool
  {
    return $actor->isAdmin();
  }

  public function assertCanDelete(User $actor): void
  {
    $this->canDelete($actor)
      || throw new ForbiddenException('Nemůžete smazat uživatele');
  }
}
