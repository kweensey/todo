<?php declare(strict_types=1);

namespace App\Utilities;

final class TokenGenerator
{
  public function generate(int $bytes = 16): string
  {
    return bin2hex(
      random_bytes($bytes)
    );
  }
}
