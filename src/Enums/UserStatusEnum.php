<?php declare(strict_types=1);

namespace App\Enums;

enum UserStatusEnum: int
{
  case DRAFT   = 0;
  case INVITE  = 1;
  case ACTIVE  = 2;
  case BLOCKED = 3;

  public function getTitle(): string
  {
    return match ($this) {
      self::DRAFT   => 'Koncept',
      self::INVITE  => 'Pozvánka',
      self::ACTIVE  => 'Aktivní',
      self::BLOCKED => 'Blokovaný'
    };
  }
}
