<?php declare(strict_types=1);

namespace App\Models;

use App\Exceptions\DomainStateException;
use App\Exceptions\UnauthorizedException;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use DateTimeInterface;

#[Table(key: 'token_id', keyType: 'string', incrementing: false, timestamps: false)]
#[Hidden(['token_hash'])]
final class RefreshToken extends Model
{
  protected function casts(): array
  {
    return [
      'issued_at'  => 'datetime',
      'expires_at' => 'datetime',
      'used_at'    => 'datetime',
      'revoked_at' => 'datetime'
    ];
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function rotatedTo(): BelongsTo
  {
    return $this->belongsTo(self::class, 'rotated_to_token_id', 'token_id');
  }

  public function isUsed(): bool
  {
    return $this->used_at !== null;
  }

  public function isExpired(DateTimeInterface $now): bool
  {
    return $this->expires_at <= $now;
  }

  public function isRevoked(): bool
  {
    return $this->revoked_at !== null;
  }

  public function isOwnedBy(User $user): bool
  {
    return $this->user_id === $user->getKey();
  }

  public function assertOwnedBy(User $user): void
  {
    $this->isOwnedBy($user)
      || throw new UnauthorizedException('Invalid token');
  }

  public function rotateTo(self $token): self
  {
    $this->user_id === $token->user_id
      && $this->family_id === $token->family_id
      || throw new DomainStateException('Invalid token rotation');

    $this->rotated_to_token_id = $token->token_id;

    return $this;
  }

  public function markUsed(DateTimeInterface $now): self
  {
    $this->used_at = $now;

    return $this;
  }
}
