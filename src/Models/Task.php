<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasBinaryUuids;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Hidden(['id', 'user_id'])]
final class Task extends Model
{
  use HasBinaryUuids;

  protected $fillable = [
    'title'
  ];

  protected function casts(): array
  {
    return [
      'completed' => 'boolean'
    ];
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function isCompleted(): bool
  {
    return $this->completed;
  }

  public function complete(): self
  {
    $this->completed = true;

    return $this;
  }

  public function reopen(): self
  {
    $this->completed = false;

    return $this;
  }
}
