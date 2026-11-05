<?php declare(strict_types=1);

namespace App\UseCases\Task;

use App\Exceptions\NotFoundException;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as DB;

final class Delete
{
  public function __invoke(string $uuid, User $actor): Task
  {
    return DB::transaction(function () use ($uuid, $actor): Task {
      $task = $this->loadTask($uuid, $actor);

      $task->delete();

      return $task;
    });
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
}
