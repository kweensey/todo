<?php declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class BinaryTokenCast implements CastsAttributes
{
  public function get(Model $model, string $key, mixed $value, array $attributes): ?string
  {
    return $value;
  }

  public function set(Model $model, string $key, mixed $value, array $attributes): ?string
  {
    if ($value === null) {
      return null;
    }

    if (! is_string($value) || $value === '') {
      throw new InvalidArgumentException('Token must be a non-empty string');
    }

    return hash('sha256', $value, true);
  }
}
