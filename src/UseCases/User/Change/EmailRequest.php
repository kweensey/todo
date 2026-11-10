<?php declare(strict_types=1);

namespace App\UseCases\User\Change;

use App\Exceptions\DomainStateException;
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

  public function __invoke(array $input, User $actor): User
  {
    $this->validate($input);

    return DB::transaction(function () use ($input, $actor): User {
      $user  = $this->lockUser($actor);
      $email = $input['email'];
      $now   = new DateTimeImmutable();

      $user->assertCanVerifyOtp($now);
      $user->assertCanIssueOtp($now);
      $this->validateEmailChange($email, $user);

      $code      = $this->generator->generate();
      $challenge = $this->storeChallenge($user, $email, $code, $now);

      $this->storePendingEmail($user, $email, $now);
      
      $this->notifications->sendEmailChangeCode(
        $challenge->recipient,
        $code
      );

      return $user;
    });
  }

  private function validate(array $input): void
  {
    $errors = (clone $this->validatorPrototype)
      ->require('email')
      ->type('email', 'string')
      ->format('email', 'email')
      ->maxLength('email', 128)
      ->validate($input);

    ! $errors
      || throw new ValidationException($errors);
  }

  private function lockUser(User $user): User
  {
    return User::query()
      ->whereKey($user->id)
      ->registered()
      ->lockForUpdate()
      ->first()
      ?? throw new DomainStateException('Uživatele se nepodařilo uzamknout');
  }

  private function validateEmailChange(string $email, User $user): void
  {
    $errors = (clone $this->validatorPrototype)
      ->custom(
        'email',
        Closure::fromCallable([$this, 'isDifferentEmail']),
        'Musí být jiný než aktuální',
        $user
      )
      ->custom(
        'email',
        Closure::fromCallable([$this, 'isEmailAvailable']),
        'Již použito'
      )
      ->validate(compact('email'));

    ! $errors
      || throw new ValidationException($errors);
  }

  private function isDifferentEmail(string $email, User $user): bool
  {
    return strcasecmp($email, $user->email) !== 0;
  }

  private function isEmailAvailable(string $email): bool
  {
    return ! User::query()
      ->where('email', $email)
      ->exists();
  }

  private function storePendingEmail(User $user, string $email, DateTimeInterface $now): void
  {
    $user
      ->requestEmail($email)
      ->recordOtpIssued($now)
      ->save();
  }

  private function storeChallenge(User $user, string $recipient, string $code, DateTimeInterface $now): Challenge
  {
    return ($this->createChallenge)($user, $recipient, $code, $now);
  }
}
