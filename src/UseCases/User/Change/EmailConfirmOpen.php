<?php declare(strict_types=1);

namespace App\UseCases\User\Change;

use App\Exceptions\DomainStateException;
use App\Exceptions\NotFoundException;
use App\Models\Challenge;
use App\Models\User;
use DateTimeImmutable;

final class EmailConfirmOpen
{
  public function __invoke(User $actor): array
  {
    $this->ensureHasPendingEmail($actor);

    $challenge = $this->loadChallenge($actor);
    $now       = new DateTimeImmutable();

    $challenge->assertNotExpired($now);
    $challenge->assertAttemptsRemaining();
    $actor->assertCanVerifyOtp($now);

    return $this->presentPage($challenge);
  }

  private function ensureHasPendingEmail(User $user): void
  {
    $user->hasPendingEmail()
      || throw new DomainStateException('Uživatel nemá zažádanou změnu e-mailu');
  }

  private function loadChallenge(User $user): Challenge
  {
    return Challenge::query()
      ->whereKey($user->getKey())
      ->where('recipient', $user->email_new)
      ->first()
      ?? throw new NotFoundException('Ověření nenalezeno');
  }

  private function presentPage(Challenge $challenge): array
  {
    return [
      'challenge' => $challenge
    ];
  }
}
