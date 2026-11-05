<?php declare(strict_types=1);

namespace App\UseCases\User\Register;

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

  public function __invoke(string $uuid, array $input): User
  {
    $this->validate($input);

    $result = DB::transaction(function () use ($uuid, $input): User|array {
      $user      = $this->loadUser($uuid, $input['token']);
      $challenge = $this->loadChallenge($user);
      $now       = new DateTimeImmutable();

      $user->assertInviteNotExpired($now);
      $challenge->assertNotExpired($now);
      $challenge->assertAttemptsRemaining();
      $user->assertCanVerifyOtp($now);
      $this->assertEmailIsAvailable($user);

      $errors = $this->verifyCode($input['code'], $challenge);

      if ($errors) {
        $this->failVerification($user, $challenge, $now);

        return $errors;
      }

      $this->storeEmail($user);
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
      ->require('code', 'token')
      ->type('code', 'string')
      ->format('code', 'numeric')
      ->length('code', 6)
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
      ->whereNotNull('email_new')
      ->lockForUpdate()
      ->first()
      ?? throw new NotFoundException('Uživatel nenalezen');
  }

  private function loadChallenge(User $user): Challenge
  {
    return Challenge::query()
      ->whereKey($user->getKey())
      ->where('recipient', $user->email_new)
      ->first()
      ?? throw new NotFoundException('Ověření nenalezeno');
  }

  private function assertEmailIsAvailable(User $user): void
  {
    ! User::query()
      ->where('email', $user->email_new)
      ->where('id', '!=', $user->id)
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

  private function storeEmail(User $user): void
  {
    $user
      ->confirmEmail()
      ->resetOtpFailures()
      ->save();
  }
}
