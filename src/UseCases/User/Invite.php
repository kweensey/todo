<?php declare(strict_types=1);

namespace App\UseCases\User;

use App\Exceptions\NotFoundException;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\EmailNotifier;
use App\Utilities\TokenGenerator;
use Illuminate\Database\Capsule\Manager as DB;
use DateTimeImmutable;
use DateTimeInterface;

final class Invite
{
  public function __construct(
    private UserPolicy $policy,
    private TokenGenerator $generator,
    private EmailNotifier $notifications
  ) {}

  public function __invoke(string $uuid, User $actor): User
  {
    $this->policy->assertCanInvite($actor);

    return DB::transaction(function () use ($uuid): User {
      $user = $this->loadUser($uuid);
      $now  = new DateTimeImmutable();

      $user->assertInvitable($now);

      $token = $this->generator->generate();

      $this->storeInvite($user, $token, $now);
      $this->notifications->sendInvitation($user, $token);

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

  private function storeInvite(User $user, string $token, DateTimeInterface $invitedAt): void
  {
    $user
      ->invite($token, $invitedAt)
      ->save();
  }
}
