<?php declare(strict_types=1);

namespace App\Utilities;

final class PasswordHasher
{
  public function hash(string $password): string
  {
    return password_hash(
      $password,
      PASSWORD_ARGON2ID,
      [
        'memory_cost' => 19456,
        'time_cost'   => 2,
        'threads'     => 1
      ]
    );
  }

  public function verify(string $password, string $hash): bool
  {
    return password_verify(
      $password,
      $hash
    );
  }
}
