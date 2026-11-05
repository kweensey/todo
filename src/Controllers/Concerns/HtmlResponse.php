<?php declare(strict_types=1);

namespace App\Controllers\Concerns;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Twig\Environment;

trait HtmlResponse
{
  abstract protected function template(): Environment;

  protected function responseHtml(string $path, array $body = [], int $status = 200): ResponseInterface
  {
    return new Response(
      $status,
      ['Content-Type' => 'text/html; charset=utf-8'],
      $this->template()->render($path, $body)
    );
  }
}
