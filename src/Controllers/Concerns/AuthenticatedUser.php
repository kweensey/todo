<?php declare(strict_types=1);

namespace App\Controllers\Concerns;

use App\Models\User;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

trait AuthenticatedUser
{
  protected static function getAuthenticatedUser(ServerRequestInterface $request): User
  {
    return $request
      ->getAttribute(User::class)
      ?? throw new RuntimeException('Authenticated user missing');
  }
}
