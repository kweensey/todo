<?php declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ValidationException extends Exception
{
  public function __construct(
    protected array $errors = []
  ) {
    parent::__construct('Payload invalid', 422);
  }

  public function getErrors(): array
  {
    return $this->errors;
  }
}
