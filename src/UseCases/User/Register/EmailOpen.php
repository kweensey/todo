<?php declare(strict_types=1);

namespace App\UseCases\User\Register;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\User;
use Oasys\Validation\Validator;
use DateTimeImmutable;

final class EmailOpen
{
  public function __construct(
    private Validator $validatorPrototype
  ) {}

  public function __invoke(string $uuid, array $input): array
  {
    $this->validate($input);

    $token = $input['token'];
    $user  = $this->loadUser($uuid, $token);
    $now   = new DateTimeImmutable();

    $user->assertInviteNotExpired($now);

    return $this->presentPage($user, $token);
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
      ->first()
      ?? throw new NotFoundException('Uživatel nenalezen');
  }

  private function presentPage(User $user, string $token): array
  {
    return [
      'user'  => $user,
      'token' => $token
    ];
  }
}
