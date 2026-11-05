<?php declare(strict_types=1);

namespace App\Models;

use App\Casts\BinaryTokenCast;
use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Exceptions\DomainStateException;
use App\Exceptions\TooManyAttemptsException;
use App\Models\Concerns\HasBinaryUuids;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

#[Hidden(['id', 'created_by_id', 'password', 'invite_token_hash', 'recovery_token_hash'])]
final class User extends Model
{
  use HasBinaryUuids;
  use SoftDeletes;

  private const LOGIN_COOLDOWN           = 'PT5M';
  private const LOGIN_MAX_ATTEMPTS       = 5;
  private const INVITE_COOLDOWN          = 'PT30M';
  private const INVITE_LIFESPAN          = 'P14D';
  private const RECOVERY_COOLDOWN        = 'PT60S';
  private const RECOVERY_ISSUANCE_WINDOW = 'PT24H';
  private const RECOVERY_LIFESPAN        = 'PT1H';
  private const RECOVERY_MAX_ISSUED      = 10;
  private const OTP_ATTEMPT_WINDOW       = 'PT1H';
  private const OTP_MAX_ATTEMPTS         = 20;
  private const OTP_ISSUANCE_WINDOW      = 'PT24H';
  private const OTP_MAX_ISSUED           = 10;

  protected $fillable = [
    'role',
    'email',
    'email_new'
  ];

  protected function casts(): array
  {
    return [
      'status'                       => UserStatusEnum::class,
      'role'                         => UserRoleEnum::class,
      'invite_date'                  => 'datetime',
      'register_date'                => 'datetime',
      'login_date'                   => 'datetime',
      'failed_login_count'           => 'integer',
      'failed_login_at'              => 'datetime',
      'recovery_requested_at'        => 'datetime',
      'recovery_issued_count'        => 'integer',
      'recovery_issuance_started_at' => 'datetime',
      'otp_failed_attempts'          => 'integer',
      'otp_started_at'               => 'datetime',
      'otp_issued_count'             => 'integer',
      'otp_issuance_started_at'      => 'datetime',
      'invite_token_hash'            => BinaryTokenCast::class,
      'recovery_token_hash'          => BinaryTokenCast::class
    ];
  }

  public function creator(): BelongsTo
  {
    return $this->belongsTo(self::class, 'created_by_id');
  }

  public function createdUsers(): HasMany
  {
    return $this->hasMany(self::class, 'created_by_id');
  }

  public function tasks(): HasMany
  {
    return $this->hasMany(Task::class);
  }

  #[Scope]
  protected function invited(Builder $query): void
  {
    $query->where('status', UserStatusEnum::INVITE);
  }

  #[Scope]
  protected function registered(Builder $query): void
  {
    $query->where('status', UserStatusEnum::ACTIVE);
  }

  #[Scope]
  protected function whereInvite(Builder $query, string $uuid, string $token): void
  {
    $tokenHash = hash('sha256', $token, true);

    $query
      ->where('uuid', $uuid)
      ->where('invite_token_hash', $tokenHash)
      ->invited();
  }

  #[Scope]
  protected function whereRecovery(Builder $query, string $uuid, string $token): void
  {
    $tokenHash = hash('sha256', $token, true);

    $query
      ->where('uuid', $uuid)
      ->where('recovery_token_hash', $tokenHash)
      ->registered();
  }

  public function isDraft(): bool
  {
    return UserStatusEnum::DRAFT === $this->status;
  }

  public function isInvited(): bool
  {
    return UserStatusEnum::INVITE === $this->status;
  }

  public function isRegistered(): bool
  {
    return UserStatusEnum::ACTIVE === $this->status;
  }

  public function isBlocked(): bool
  {
    return UserStatusEnum::BLOCKED === $this->status;
  }

  public function isAdmin(): bool
  {
    return UserRoleEnum::ADMIN === $this->role;
  }

  public function isBlockable(): bool
  {
    return $this->isRegistered();
  }

  public function assertBlockable(): void
  {
    $this->isBlockable()
      || throw new DomainStateException('Neaktivního uživatele nelze zablokovat');
  }

  public function isUnblockable(): bool
  {
    return $this->isBlocked();
  }

  public function assertUnblockable(): void
  {
    $this->isUnblockable()
      || throw new DomainStateException('Aktivního uživatele nelze odblokovat');
  }

  public function isDeletable(): bool
  {
    return $this->isDraft()
      || $this->isInvited();
  }

  public function assertDeletable(): void
  {
    $this->isDeletable()
      || throw new DomainStateException('Aktivního uživatele nelze smazat');
  }

  public function isInvitable(DateTimeInterface $now): bool
  {
    if ($this->isDraft()) {
      return true;
    }

    $inviteDate = $this->invite_date;

    if (! $this->isInvited() || ! $inviteDate) {
      return false;
    }

    $cooldown = new DateInterval(self::INVITE_COOLDOWN);

    $availableAt = DateTimeImmutable::createFromInterface($inviteDate)
      ->add($cooldown);

    return $availableAt <= $now;
  }

  public function assertInvitable(DateTimeInterface $now): void
  {
    $this->isInvitable($now)
      || throw new DomainStateException('Uživatele nelze pozvat');
  }

  public function isInviteExpired(DateTimeInterface $now): bool
  {
    $invitedAt = $this->invite_date;

    if ($invitedAt === null) {
      return true;
    }

    $expiresAt = DateTimeImmutable::createFromInterface($invitedAt)
      ->add(new DateInterval(self::INVITE_LIFESPAN));

    return $expiresAt <= $now;
  }

  public function assertInviteNotExpired(DateTimeInterface $now): void
  {
    ! $this->isInviteExpired($now)
      || throw new DomainStateException('Platnost pozvánky vypršela');
  }

  public function canVerifyOtp(DateTimeInterface $now): bool
  {
    return ! $this->isOtpAttemptWindowActive($now)
      || $this->otp_failed_attempts < self::OTP_MAX_ATTEMPTS;
  }

  public function assertCanVerifyOtp(DateTimeInterface $now): void
  {
    $this->canVerifyOtp($now)
      || throw new TooManyAttemptsException('Překročen počet pokusů o ověření');
  }

  private function isOtpAttemptWindowActive(DateTimeInterface $now): bool
  {
    $startedAt = $this->otp_started_at;

    if ($startedAt === null) {
      return false;
    }

    $window = new DateInterval(self::OTP_ATTEMPT_WINDOW);

    $availableAt = DateTimeImmutable::createFromInterface($startedAt)
      ->add($window);

    return $availableAt > $now;
  }

  public function canIssueOtp(DateTimeInterface $now): bool
  {
    return ! $this->isOtpIssuanceWindowActive($now)
      || $this->otp_issued_count < self::OTP_MAX_ISSUED;
  }

  public function assertCanIssueOtp(DateTimeInterface $now): void
  {
    $this->canIssueOtp($now)
      || throw new TooManyAttemptsException('Překročen počet žádostí o ověřovací kód');
  }

  private function isOtpIssuanceWindowActive(DateTimeInterface $now): bool
  {
    $startedAt = $this->otp_issuance_started_at;

    if ($startedAt === null) {
      return false;
    }

    $window = new DateInterval(self::OTP_ISSUANCE_WINDOW);

    $availableAt = DateTimeImmutable::createFromInterface($startedAt)
      ->add($window);

    return $availableAt > $now;
  }

  public function hasRecoveryRequestsRemaining(DateTimeInterface $now): bool
  {
    return ! $this->isRecoveryIssuanceWindowActive($now)
      || $this->recovery_issued_count < self::RECOVERY_MAX_ISSUED;
  }

  public function assertRecoveryRequestsRemaining(DateTimeInterface $now): void
  {
    $this->hasRecoveryRequestsRemaining($now)
      || throw new TooManyAttemptsException('Překročen počet žádostí o obnovení hesla');
  }

  private function isRecoveryIssuanceWindowActive(DateTimeInterface $now): bool
  {
    $startedAt = $this->recovery_issuance_started_at;

    if ($startedAt === null) {
      return false;
    }

    $window = new DateInterval(self::RECOVERY_ISSUANCE_WINDOW);

    $availableAt = DateTimeImmutable::createFromInterface($startedAt)
      ->add($window);

    return $availableAt > $now;
  }

  public function canResendRecoveryRequest(DateTimeInterface $now): bool
  {
    $requestedAt = $this->recovery_requested_at;

    if ($requestedAt === null) {
      return true;
    }

    $cooldown = new DateInterval(self::RECOVERY_COOLDOWN);

    $availableAt = DateTimeImmutable::createFromInterface($requestedAt)
      ->add($cooldown);

    return $availableAt <= $now;
  }

  public function assertCanResendRecoveryRequest(DateTimeInterface $now): void
  {
    $this->canResendRecoveryRequest($now)
      || throw new TooManyAttemptsException('O obnovení hesla lze požádat až za chvíli');
  }

  public function canRequestRecovery(DateTimeInterface $now): bool
  {
    return $this->hasRecoveryRequestsRemaining($now)
      && $this->canResendRecoveryRequest($now);
  }

  public function isRecoveryExpired(DateTimeInterface $now): bool
  {
    $requestedAt = $this->recovery_requested_at;

    if ($requestedAt === null) {
      return true;
    }

    $lifespan = new DateInterval(self::RECOVERY_LIFESPAN);

    $expiresAt = DateTimeImmutable::createFromInterface($requestedAt)
      ->add($lifespan);

    return $expiresAt <= $now;
  }

  public function assertRecoveryNotExpired(DateTimeInterface $now): void
  {
    ! $this->isRecoveryExpired($now)
      || throw new DomainStateException('Obnovení hesla vypršelo');
  }

  public function hasLoginAttemptsRemaining(): bool
  {
    return $this->failed_login_count < self::LOGIN_MAX_ATTEMPTS;
  }

  public function hasReachedLoginAttemptsLimit(): bool
  {
    return $this->failed_login_count === self::LOGIN_MAX_ATTEMPTS;
  }

  public function isLoginCooldownElapsed(DateTimeInterface $now): bool
  {
    $failedAt = $this->failed_login_at;

    if ($failedAt === null) {
      return true;
    }

    $cooldown = new DateInterval(self::LOGIN_COOLDOWN);

    $availableAt = DateTimeImmutable::createFromInterface($failedAt)
      ->add($cooldown);

    return $availableAt <= $now;
  }

  public function canRequestLogin(DateTimeInterface $now): bool
  {
    if ($this->hasLoginAttemptsRemaining()) {
      return true;
    }

    return $this->isLoginCooldownElapsed($now);
  }

  public function hasPendingEmail(): bool
  {
    return $this->email_new !== null;
  }

  public function invite(string $token, DateTimeInterface $now): self
  {
    $this->status            = UserStatusEnum::INVITE;
    $this->invite_email      = $this->email;
    $this->invite_date       = $now;
    $this->invite_token_hash = $token;

    return $this;
  }

  public function requestEmail(string $newEmail): self
  {
    $this->email_new = $newEmail;

    return $this;
  }

  public function confirmEmail(): self
  {
    $this->email               = $this->email_new;
    $this->email_new           = null;
    $this->recovery_token_hash = null;

    return $this;
  }

  public function register(string $passwordHash, DateTimeInterface $now): self
  {
    $this->status            = UserStatusEnum::ACTIVE;
    $this->password          = $passwordHash;
    $this->register_date     = $now;
    $this->invite_token_hash = null;

    return $this;
  }

  public function requestRecovery(string $token, DateTimeInterface $now): self
  {
    $this->recovery_token_hash   = $token;
    $this->recovery_requested_at = $now;

    return $this;
  }

  public function confirmRecovery(string $passwordHash): self
  {
    $this->password             = $passwordHash;
    $this->recovery_token_hash  = null;
    $this->failed_login_at      = null;
    $this->failed_login_count   = 0;

    return $this;
  }

  public function changePassword(string $passwordHash): self
  {
    $this->password            = $passwordHash;
    $this->recovery_token_hash = null;

    return $this;
  }

  public function block(): self
  {
    $this->status = UserStatusEnum::BLOCKED;

    return $this;
  }

  public function unblock(): self
  {
    $this->status = UserStatusEnum::ACTIVE;

    return $this;
  }

  public function recordFailedLogin(DateTimeInterface $now): self
  {
    $this->failed_login_at = $now;

    $this->failed_login_count++;

    return $this;
  }

  public function recordLogin(DateTimeInterface $now): self
  {
    $this->login_date         = $now;
    $this->failed_login_at    = null;
    $this->failed_login_count = 0;

    return $this;
  }

  public function failOtpAttempt(DateTimeInterface $now): self
  {
    if (! $this->isOtpAttemptWindowActive($now)) {
      $this->otp_failed_attempts = 0;
      $this->otp_started_at      = $now;
    }

    $this->otp_failed_attempts++;

    return $this;
  }

  public function resetOtpFailures(): self
  {
    $this->otp_failed_attempts = 0;
    $this->otp_started_at      = null;

    return $this;
  }

  public function recordOtpIssued(DateTimeInterface $now): self
  {
    if (! $this->isOtpIssuanceWindowActive($now)) {
      $this->otp_issued_count        = 0;
      $this->otp_issuance_started_at = $now;
    }

    $this->otp_issued_count++;

    return $this;
  }

  public function recordRecoveryIssued(DateTimeInterface $now): self
  {
    if (! $this->isRecoveryIssuanceWindowActive($now)) {
      $this->recovery_issued_count        = 0;
      $this->recovery_issuance_started_at = $now;
    }

    $this->recovery_issued_count++;

    return $this;
  }
}
