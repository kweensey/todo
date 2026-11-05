<?php declare(strict_types=1);

namespace App\Models;

use App\Exceptions\DomainStateException;
use App\Exceptions\TooManyAttemptsException;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

#[Table(key: 'user_id', keyType: 'string', incrementing: false)]
#[Hidden(['user_id', 'code_hash'])]
final class Challenge extends Model
{
  private const MAX_ATTEMPTS    = 5;
  private const RESEND_COOLDOWN = 'PT60S';

  protected function casts(): array
  {
    return [
      'attempts'   => 'integer',
      'expires_at' => 'datetime'
    ];
  }

  public function isExpired(DateTimeInterface $now): bool
  {
    return $this->expires_at <= $now;
  }

  public function assertNotExpired(DateTimeInterface $now): void
  {
    ! $this->isExpired($now)
      || throw new DomainStateException('Ověřovací kód vypršel');
  }

  public function hasAttemptsRemaining(): bool
  {
    return $this->attempts < self::MAX_ATTEMPTS;
  }

  public function assertAttemptsRemaining(): void
  {
    $this->hasAttemptsRemaining()
      || throw new TooManyAttemptsException('Překročen počet pokusů o ověření');
  }

  public function isResendable(DateTimeInterface $now): bool
  {
    $createdAt   = DateTimeImmutable::createFromInterface($this->created_at);
    $cooldown    = new DateInterval(self::RESEND_COOLDOWN);
    $availableAt = $createdAt->add($cooldown);

    return $availableAt <= $now;
  }

  public function assertResendable(DateTimeInterface $now): void
  {
    $this->isResendable($now)
      || throw new TooManyAttemptsException('Nový kód lze zaslat až po chvíli');
  }

  public function failAttempt(): self
  {
    $this->attempts++;

    return $this;
  }
}
