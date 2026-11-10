<?php declare(strict_types=1);

namespace App\UseCases\Task;

use App\Exceptions\ValidationException;
use App\Models\Task;
use App\Models\User;
use Oasys\Validation\Validator;

final class Create
{
  public function __construct(
    private Validator $validatorPrototype
  ) {}

  public function __invoke(array $input, User $actor): Task
  {
    $this->validate($input);

    return $this->storeTask($actor, $input);
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

  private function storeTask(User $actor, array $input): Task
  {
    $task = new Task();

    $task->fill($input);

    $task->user_id   = $actor->id;
    $task->completed = false;

    $task->save();

    return $task;
  }
}
