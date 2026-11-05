<?php declare(strict_types=1);

namespace App\UseCases\Challenge;

use App\Models\Challenge;
use App\Models\User;
use App\Utilities\HmacHasher;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

final class Create
{
  private const LIFESPAN = 'PT10M';

  public function __construct(
    private HmacHasher $hasher
  ) {}

  public function __invoke(User $user, string $recipient, string $code, DateTimeInterface $now): Challenge
  {
    $challenge = $this->loadChallenge($user);

    $challenge?->assertResendable($now);
    $challenge?->delete();

    return $this->storeChallenge($user, $recipient, $code, $now);
  }

  private function loadChallenge(User $user): ?Challenge
  {
    return Challenge::query()
      ->whereKey($user->getKey())
      ->first();
  }

  private function storeChallenge(User $user, string $recipient, string $code, DateTimeInterface $now): Challenge
  {
    $challenge = new Challenge();

    $challenge->user_id    = $user->id;
    $challenge->created_at = $now;
    $challenge->updated_at = $now;
    $challenge->expires_at = $this->getExpiration($now);
    $challenge->recipient  = $recipient;
    $challenge->attempts   = 0;
    $challenge->code_hash  = $this->hasher->hash($code);

    $challenge->save();

    return $challenge;
  }

  private function getExpiration(DateTimeInterface $now): DateTimeImmutable
  {
    $lifespan = new DateInterval(self::LIFESPAN);

    return DateTimeImmutable::createFromInterface($now)
      ->add($lifespan);
  }
}
