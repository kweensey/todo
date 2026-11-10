<?php declare(strict_types=1);

namespace App\UseCases\Task;

use App\Exceptions\NotFoundException;
use App\Models\Task;
use App\Models\User;

final class UpdateOpen
{
  public function __invoke(string $uuid, User $actor): array
  {
    $task = $this->loadTask($uuid, $actor);

    return $this->presentPage($actor, $task);
  }

  private function loadTask(string $uuid, User $actor): Task
  {
    return Task::query()
      ->where('uuid', $uuid)
      ->where('user_id', $actor->id)
      ->first()
      ?? throw new NotFoundException('Úkol nenalezen');
  }

  private function presentPage(User $actor, Task $task): array
  {
    return [
      'actor' => $actor,
      'item'  => $task
    ];
  }
}
