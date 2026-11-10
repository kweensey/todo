<?php declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use DateTimeInterface;

final class RevokeAll
{
  public function __invoke(User $user, DateTimeInterface $now): void
  {
    RefreshToken::query()
      ->where('user_id', $user->getKey())
      ->whereNull('revoked_at')
      ->update([
        'revoked_at' => $now
      ]);
  }
}
