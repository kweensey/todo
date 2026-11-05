<?php declare(strict_types=1);

namespace App\Utilities;

use Oasys\Config\ArrayConfig as Config;

final class HmacHasher
{
  public function __construct(
    private Config $config
  ) {}

  public function hash(string $value): string
  {
    return hash_hmac(
      'sha256',
      $value,
      $this->config->get('app.hmacKey'),
      true
    );
  }

  public function verify(string $value, string $hash): bool
  {
    return hash_equals(
      $hash,
      $this->hash($value)
    );
  }
}
