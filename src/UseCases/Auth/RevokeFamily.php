<?php declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Models\RefreshToken;
use DateTimeInterface;

final class RevokeFamily
{
  public function __invoke(string $familyId, DateTimeInterface $now): void
  {
    RefreshToken::query()
      ->where('family_id', $familyId)
      ->whereNull('revoked_at')
      ->update([
        'revoked_at' => $now
      ]);
  }
}
