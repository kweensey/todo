<?php declare(strict_types=1);

namespace App\UseCases\User\Register;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\User;
use App\Services\EmailNotifier;
use App\Utilities\PasswordHasher;
use Illuminate\Database\Capsule\Manager as DB;
use Oasys\Validation\Validator;
use Psr\Log\LoggerInterface;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

final class Password
{
  public function __construct(
    private Validator $validatorPrototype,
    private LoggerInterface $logger,
    private PasswordHasher $hasher,
    private EmailNotifier $notifications
  ) {}

  public function __invoke(string $uuid, array $input): User
  {
    $this->validate($input);

    $user = DB::transaction(function () use ($uuid, $input): User {
      $token = $input['token'];
      $user  = $this->loadUser($uuid, $token);
      $now   = new DateTimeImmutable();

      $user->assertInviteNotExpired($now);
      $this->storeRegistrationStep($user, $input['password'], $now);

      return $user;
    });

    try {
      $this->notifications->sendWelcome($user);
    } catch (Throwable $exception) {
      $this->logger->warning('Failed to send welcome email', [
        'user_uuid' => $user->uuid,
        'exception' => $exception
      ]);
    }

    return $user;
  }

  private function validate(array $input): void
  {
    $errors = (clone $this->validatorPrototype)
      ->require('password', 'password_check', 'token')
      ->type('password', 'string')
      ->minLength('password', 15)
      ->maxLength('password', 128)
      ->type('password_check', 'string')
      ->equalToField('password', 'password_check')
      ->type('token', 'string')
      ->format('token', 'hexadecimal')
      ->length('token', 32)
      ->validate($input);

    ! $errors
      || throw new ValidationException($errors);
  }

  private function loadUser(string $uuid, string $token): User
  {
    return User::query()
      ->whereInvite($uuid, $token)
      ->whereNull('email_new')
      ->lockForUpdate()
      ->first()
      ?? throw new NotFoundException('Uživatel nenalezen');
  }

  private function storeRegistrationStep(User $user, string $password, DateTimeInterface $now): void
  {
    $hash = $this->hasher->hash($password);

    $user
      ->register($hash, $now)
      ->save();
  }
}
