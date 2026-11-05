<?php declare(strict_types=1);

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use InvalidArgumentException;

final class TimingMiddleware implements MiddlewareInterface
{
  public function __construct(
    private int $min
  ) {
    if ($min < 0) {
      throw new InvalidArgumentException('Minimum duration cannot be negative');
    }
  }

  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
  {
    $startedAt = hrtime(true);

    try {
      return $handler->handle($request);
    } finally {
      $elapsed   = hrtime(true) - $startedAt;
      $remaining = $this->min * 1_000_000 - $elapsed;

      if ($remaining > 0) {
        time_nanosleep(
          intdiv($remaining, 1_000_000_000),
          $remaining % 1_000_000_000
        );
      }
    }
  }
}
