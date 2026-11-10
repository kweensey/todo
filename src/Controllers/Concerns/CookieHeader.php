<?php declare(strict_types=1);

namespace App\Controllers\Concerns;

trait CookieHeader
{
  protected static function cookie(string $name, string $value, string $path, bool $httpOnly, bool $expired = false): string
  {
    return $name
      . '='
      . $value
      . '; Path='
      . $path
      . ($expired ? '; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT' : '')
      . ($httpOnly ? '; HttpOnly' : '')
      . '; Secure; SameSite=Lax';
  }
}
