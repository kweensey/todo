<?php declare(strict_types=1);

namespace App\UseCases\User\Change;

use App\Exceptions\DomainStateException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Challenge;
use App\Models\User;
use App\Utilities\HmacHasher;
use Illuminate\Database\Capsule\Manager as DB;
use Oasys\Validation\Validator;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;

final class EmailConfirm
{
  public function __construct(
    private Validator $validatorPrototype,
    private HmacHasher $hasher
  ) {}

  public function __invoke(array $input, User $actor): User
  {
    $this->validate($input);

    $result = DB::transaction(function () use ($input, $actor): User|array {
      $user = $this->lockUser($actor);

      $this->ensureHasPendingEmail($user);

      $challenge = $this->loadChallenge($user);
      $now       = new DateTimeImmutable();

      $challenge->assertNotExpired($now);
      $challenge->assertAttemptsRemaining();
      $user->assertCanVerifyOtp($now);
      $this->ensureEmailIsAvailable($user);

      $errors = $this->verifyCode($input['code'], $challenge);

      if ($errors) {
        $this->failVerification($user, $challenge, $now);

        return $errors;
      }

      $this->storeChangedEmail($user);
      $challenge->delete();

      return $user;
    });

    is_array($result)
      && throw new ValidationException($result);

    return $result;
  }

  private function validate(array $input): void
  {
    $errors = (clone $this->validatorPrototype)
      ->require('code')
      ->type('code', 'string')
      ->format('code', 'numeric')
      ->length('code', 6)
      ->validate($input);

    ! $errors
      || throw new ValidationException($errors);
  }

  private function lockUser(User $user): User
  {
    return User::query()
      ->whereKey($user->getKey())
      ->registered()
      ->lockForUpdate()
      ->first()
      ?? throw new DomainStateException('Uživatele se nepodařilo uzamknout');
  }

  private function ensureHasPendingEmail(User $user): void
  {
    $user->hasPendingEmail()
      || throw new DomainStateException('Uživatel nemá rozpracovanou změnu e-mailu');
  }

  private function loadChallenge(User $user): Challenge
  {
    return Challenge::query()
      ->whereKey($user->getKey())
      ->where('recipient', $user->email_new)
      ->first()
      ?? throw new NotFoundException('Ověření nenalezeno');
  }

  private function ensureEmailIsAvailable(User $user): void
  {
    ! User::query()
      ->where('email', $user->email_new)
      ->where('id', '!=', $user->getKey())
      ->exists()
      || throw new DomainStateException('E-mail je již použitý');
  }

  private function verifyCode(string $code, Challenge $challenge): array
  {
    return (clone $this->validatorPrototype)
      ->require('code')
      ->custom(
        'code',
        Closure::fromCallable([$this, 'isCodeValid']),
        'Nesprávný kód',
        $challenge
      )
      ->validate(compact('code'));
  }

  private function isCodeValid(string $code, Challenge $challenge): bool
  {
    return $this->hasher->verify($code, $challenge->code_hash);
  }

  private function failVerification(User $user, Challenge $challenge, DateTimeInterface $now): void
  {
    $user
      ->failOtpAttempt($now)
      ->save();

    $challenge
      ->failAttempt()
      ->save();
  }

  private function storeChangedEmail(User $user): void
  {
    $user
      ->confirmEmail()
      ->resetOtpFailures()
      ->save();
  }
}
