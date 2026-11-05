<?php declare(strict_types=1);

namespace App\Controllers\Concerns;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

trait PlainResponse
{
  protected static function responsePlain(int $status = 204, ?string $body = null): ResponseInterface
  {
    return new Response(
      $status,
      ['Content-Type' => 'text/plain; charset=utf-8'],
      $body
    );
  }
}
