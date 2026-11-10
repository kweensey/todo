<?php declare(strict_types=1);

namespace App\UseCases\User\Register;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Challenge;
use App\Models\User;
use Oasys\Validation\Validator;
use DateTimeImmutable;

final class EmailConfirmOpen
{
  public function __construct(
    private Validator $validatorPrototype
  ) {}

  public function __invoke(string $uuid, array $input): array
  {
    $this->validate($input);

    $token     = $input['token'];
    $user      = $this->loadUser($uuid, $token);
    $challenge = $this->loadChallenge($user);
    $now       = new DateTimeImmutable();

    $user->assertInviteNotExpired($now);
    $challenge->assertNotExpired($now);
    $challenge->assertAttemptsRemaining();
    $user->assertCanVerifyOtp($now);

    return $this->presentPage($challenge, $token);
  }

  private function validate(array $input): void
  {
    $errors = (clone $this->validatorPrototype)
      ->require('token')
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

  private function presentPage(Challenge $challenge, string $token): array
  {
    return [
      'token'     => $token,
      'challenge' => $challenge
    ];
  }
}
