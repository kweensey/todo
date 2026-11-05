<?php declare(strict_types=1);

namespace App\Controllers\Concerns;

use App\Exceptions\BadRequestException;
use Psr\Http\Message\ServerRequestInterface;
use JsonException;

trait RequestData
{
  protected static function getJsonPayload(ServerRequestInterface $request): array
  {
    $json = $request->getBody()->getContents();

    try {
      $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      throw new BadRequestException('Malformed JSON payload');
    }

    is_array($payload)
      && str_starts_with(ltrim($json), '{')
      || throw new BadRequestException('JSON payload must be an object');

    return $payload;
  }

  protected static function getUriParts(ServerRequestInterface $request): array
  {
    $uri = $request
      ->getUri()
      ->getPath();

    $uri = trim($uri, '/');

    return explode('/', $uri);
  }
}
