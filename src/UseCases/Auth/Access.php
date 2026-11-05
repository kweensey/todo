<?php declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Exceptions\ValidationException;
use App\Models\User;
use App\Services\Auth\TokenIssuer;
use App\Services\EmailNotifier;
use App\Utilities\PasswordHasher;
use Illuminate\Database\Capsule\Manager as DB;
use Oasys\Validation\Validator;
use Psr\Log\LoggerInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

final class Access
{
  private const DUMMY_PASSWORD_HASH = '$argon2id$v=19$m=19456,t=2,p=1$NHV0b20yL3VHOXV3VUdtWQ$tU+CvJAkqqezHn2AJVlIpyIB9i3b/OwiY2UqlpjJAQ0';

  public function __construct(
    private TokenIssuer $tokenIssuer,
    private Validator $validatorPrototype,
    private LoggerInterface $logger,
    private PasswordHasher $hasher,
    private EmailNotifier $notifications
  ) {}

  public function __invoke(array $input): array
  {
    $this->validate($input);

    [$tokens, $lockedOutUser] = DB::transaction(function () use ($input): array {
      $user = $this->loadUser($input['email']);
      $now  = new DateTimeImmutable();

      $canRequest = $user?->canRequestLogin($now)
        ?? false;

      $passed = $this->authenticate($user, $input['password'])
        && $canRequest;

      $tokens        = null;
      $lockedOutUser = null;

      if ($passed) {
        $this->storeAccess($user, $now);

        $tokens = $this->tokenIssuer->issue($user);
      } else if ($canRequest) {
        $this->storeLoginAttempt($user, $now);

        $lockedOutUser = $user->hasReachedLoginAttemptsLimit()
          ? $user
          : null;
      }

      return [$tokens, $lockedOutUser];
    });

    if ($lockedOutUser !== null) {
      try {
        $this->notifications->sendLoginLockout($lockedOutUser);
      } catch (Throwable $exception) {
        $this->logger->warning('Failed to send account lockout notification', [
          'user_uuid' => $lockedOutUser->uuid,
          'exception' => $exception
        ]);
      }
    }

    return $tokens
      ?? throw new ValidationException(['password' => 'Neplatné údaje']);
  }

  private function validate(array $input): void
  {
    $errors = (clone $this->validatorPrototype)
      ->require('email', 'password')
      ->type('email', 'string')
      ->format('email', 'email')
      ->maxLength('email', 128)
      ->type('password', 'string')
      ->validate($input);

    ! $errors
      || throw new ValidationException($errors);
  }

  private function loadUser(string $email): ?User
  {
    return User::query()
      ->where('email', $email)
      ->registered()
      ->lockForUpdate()
      ->first();
  }

  private function authenticate(?User $user, string $password): bool
  {
    $hash = $user?->password
      ?? self::DUMMY_PASSWORD_HASH;

    return $this->hasher->verify($password, $hash);
  }

  private function storeLoginAttempt(User $user, DateTimeInterface $now): void
  {
    $user
      ->recordFailedLogin($now)
      ->save();
  }

  private function storeAccess(User $user, DateTimeInterface $now): void
  {
    $user
      ->recordLogin($now)
      ->save();
  }
}
