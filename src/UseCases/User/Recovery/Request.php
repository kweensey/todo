<?php declare(strict_types=1);

namespace App\UseCases\User\Recovery;

use App\Exceptions\SmtpException;
use App\Exceptions\ValidationException;
use App\Models\User;
use App\Services\EmailNotifier;
use App\Utilities\TokenGenerator;
use Oasys\Validation\Validator;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Log\LoggerInterface;
use DateTimeImmutable;
use DateTimeInterface;

final class Request
{
  public function __construct(
    private Validator $validatorPrototype,
    private LoggerInterface $logger,
    private TokenGenerator $generator,
    private EmailNotifier $notifications
  ) {}

  public function __invoke(array $input): ?User
  {
    $this->validate($input);

    try {
      return DB::transaction(function () use ($input): ?User {
        $user = $this->loadUser($input['email']);

        if ($user === null) {
          return null;
        }

        $now = new DateTimeImmutable();

        if (! $user->canRequestRecovery($now)) {
          return null;
        }

        $token = $this->generator->generate();

        $this->storeRecovery($user, $token, $now);
        $this->notifications->sendRecovery($user, $token);

        return $user;
      });
    } catch (SmtpException $exception) {
      $this->logger->error('Failed to send password recovery email', [
        'exception' => $exception
      ]);

      return null;
    }
  }

  private function validate(array $input): void
  {
    $errors = (clone $this->validatorPrototype)
      ->require('email')
      ->type('email', 'string')
      ->format('email', 'email')
      ->maxLength('email', 128)
      ->validate($input);

    ! $errors
      || throw new ValidationException($errors);
  }

  private function loadUser(string $email): ?User
  {
    return User::query()
      ->where('email', $email)
      ->registered()
      ->lockForUpdate()
      ->first();
  }

  private function storeRecovery(User $user, string $token, DateTimeInterface $now): void
  {
    $user
      ->requestRecovery($token, $now)
      ->recordRecoveryIssued($now)
      ->save();
  }
}
