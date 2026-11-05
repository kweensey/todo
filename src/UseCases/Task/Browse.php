<?php declare(strict_types=1);

namespace App\UseCases\Task;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class Browse
{
  public function __invoke(User $actor): array
  {
    $tasks = $this->loadTasks($actor);

    return $this->presentPage($actor, $tasks);
  }

  private function loadTasks(User $actor): Collection
  {
    return Task::query()
      ->where('user_id', $actor->id)
      ->orderBy('completed')
      ->orderByDesc('created_at')
      ->get();
  }

  private function presentPage(User $actor, Collection $items): array
  {
    return [
      'actor' => $actor,
      'items' => $items,
      'count' => $items->count()
    ];
  }
}
