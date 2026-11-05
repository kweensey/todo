<?php declare(strict_types=1);

namespace App\Utilities;

final class CodeGenerator
{
  public function generate(int $digits = 6): string
  {
    $max  = (10 ** $digits) - 1;
    $code = (string) random_int(0, $max);

    return str_pad($code, $digits, '0', STR_PAD_LEFT);
  }
}
