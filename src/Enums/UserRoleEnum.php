<?php declare(strict_types=1);

namespace App\Enums;

enum UserRoleEnum: int
{
  case USER  = 0;
  case ADMIN = 1;

  public function getTitle(): string
  {
    return match ($this) {
      self::USER  => 'Uživatel',
      self::ADMIN => 'Administrátor'
    };
  }
}
