<?php declare(strict_types=1);

namespace App\UseCases\User\Recovery;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\User;
use Oasys\Validation\Validator;
use DateTimeImmutable;

final class PasswordOpen
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

    $user->assertRecoveryNotExpired($now);

    return $this->presentPage($token);
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
      ->whereRecovery($uuid, $token)
      ->first()
      ?? throw new NotFoundException('Uživatel nenalezen');
  }

  private function presentPage(string $token): array
  {
    return [
      'token' => $token
    ];
  }
}
