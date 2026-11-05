<?php declare(strict_types=1);

namespace App\Controllers\Concerns;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

trait JsonResponse
{
  protected static function responseJson(int $status = 200, array $body = []): ResponseInterface
  {
    return new Response(
      $status,
      ['Content-Type' => 'application/json; charset=utf-8'],
      json_encode($body, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );
  }
}
