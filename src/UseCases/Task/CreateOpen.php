<?php declare(strict_types=1);

namespace App\UseCases\Task;

use App\Models\User;

final class CreateOpen
{
  public function __invoke(User $actor): array
  {
    return [
      'actor' => $actor
    ];
  }
}
