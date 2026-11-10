<?php declare(strict_types=1);

namespace App\Models\Concerns;

use App\Casts\BinaryUuidCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Ramsey\Uuid\Uuid;

trait HasBinaryUuids
{
  use HasUuids;

  protected function initializeHasBinaryUuids(): void
  {
    $this->mergeCasts([
      $this->getKeyName() => BinaryUuidCast::class
    ]);
  }

  public function getUuid(): string
  {
    $key = $this->getKeyName();
    $id  = $this->{$key};

    return strtoupper(
      Uuid::fromBytes($id)
        ->toString()
    );
  }
}
