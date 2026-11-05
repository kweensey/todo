<?php declare(strict_types=1);

namespace App\UseCases\Task;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as DB;
use Oasys\Validation\Validator;

final class Update
{
  public function __construct(
    private Validator $validatorPrototype
  ) {}

  public function __invoke(string $uuid, array $input, User $actor): Task
  {
    $this->validate($input);

    return DB::transaction(function () use ($uuid, $input, $actor): Task {
      $task = $this->loadTask($uuid, $actor);

      $this->storeTask($task, $input);

      return $task;
    });
  }

  private function validate(array $input): void
  {
    $errors = (clone $this->validatorPrototype)
      ->require('title')
      ->type('title', 'string')
      ->maxLength('title', 255)
      ->validate($input);

    ! $errors
      || throw new ValidationException($errors);
  }

  private function loadTask(string $uuid, User $actor): Task
  {
    return Task::query()
      ->where('uuid', $uuid)
      ->where('user_id', $actor->id)
      ->lockForUpdate()
      ->first()
      ?? throw new NotFoundException('Úkol nenalezen');
  }

  private function storeTask(Task $task, array $input): void
  {
    $task
      ->fill($input)
      ->save();
  }
}
