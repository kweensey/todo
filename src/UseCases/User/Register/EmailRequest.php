<?php declare(strict_types=1);

namespace App\UseCases\User\Register;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Challenge;
use App\Models\User;
use App\Services\EmailNotifier;
use App\UseCases\Challenge\Create as CreateChallenge;
use App\Utilities\CodeGenerator;
use Illuminate\Database\Capsule\Manager as DB;
use Oasys\Validation\Validator;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;

final class EmailRequest
{
  public function __construct(
    private CreateChallenge $createChallenge,
    private Validator $validatorPrototype,
    private CodeGenerator $generator,
    private EmailNotifier $notifications
  ) {}

  public function __invoke(string $uuid, array $input): User
  {
    $this->validate($input);

    return DB::transaction(function () use ($uuid, $input): User {
      $user  = $this->loadUser($uuid, $input['token']);
      $email = $input['email'];
      $now   = new DateTimeImmutable();

      $user->assertInviteNotExpired($now);
      $user->assertCanVerifyOtp($now);
      $user->assertCanIssueOtp($now);
      $this->validateEmailAvailability($email, $uuid);

      $code      = $this->generator->generate();
      $challenge = $this->storeChallenge($user, $email, $code, $now);

      $this->storeRegistrationStep($user, $email, $now);

      $this->notifications->sendRegistrationCode(
        $challenge->recipient,
        $code
      );

      return $user;
    });
  }

  private function validate(array $input): void
  {
    $errors = (clone $this->validatorPrototype)
      ->require('email', 'token')
      ->type('email', 'string')
      ->format('email', 'email')
      ->maxLength('email', 128)
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
      ->lockForUpdate()
      ->first()
      ?? throw new NotFoundException('Uživatel nenalezen');
  }

  private function validateEmailAvailability(string $email, string $uuid): void
  {
    $errors = (clone $this->validatorPrototype)
      ->custom(
        'email',
        Closure::fromCallable([$this, 'isEmailAvailable']),
        'Již existuje',
        $uuid
      )
      ->validate(compact('email'));

    ! $errors
      || throw new ValidationException($errors);
  }

  private function isEmailAvailable(string $email, string $except): bool
  {
    return ! User::query()
      ->where('email', $email)
      ->where('uuid', '!=', $except)
      ->exists();
  }

  private function storeChallenge(User $user, string $recipient, string $code, DateTimeInterface $now): Challenge
  {
    return ($this->createChallenge)($user, $recipient, $code, $now);
  }

  private function storeRegistrationStep(User $user, string $email, DateTimeInterface $now): void
  {
    $user
      ->requestEmail($email)
      ->recordOtpIssued($now)
      ->save();
  }
}
