<?php declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\HtmlResponse;
use App\Controllers\Concerns\JsonResponse;
use App\Controllers\Concerns\PlainResponse;
use App\Exceptions\RecoverableSessionException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment as Twig;
use Throwable;

final class ErrorController
{
  use PlainResponse;
  use JsonResponse;
  use HtmlResponse;

  public function __construct(
    private LoggerInterface $logger,
    private Twig $twig
  ) {}

  protected function template(): Twig
  {
    return $this->twig;
  }

  public function serverError(ServerRequestInterface $request): ResponseInterface
  {
    $this->logger->error('Unhandled HTTP exception', [
      'exception' => $request->getAttribute(Throwable::class),
      'method'    => $request->getMethod(),
      'path'      => $request->getUri()->getPath()
    ]);

    return self::responsePlain(500, "Chyba serveru\n")
      ->withHeader('Cache-Control', 'no-store');
  }

  public function notFound(ServerRequestInterface $request): ResponseInterface
  {
    return self::responsePlain(404, "Nenalezeno\n")
      ->withHeader('Cache-Control', 'no-store');
  }

  public function badRequest(ServerRequestInterface $request): ResponseInterface
  {
    $message = self::message($request);

    return self::responsePlain(400, $message . "\n")
      ->withHeader('Cache-Control', 'no-store');
  }

  public function authorizationError(ServerRequestInterface $request): ResponseInterface
  {
    if ($this->isRecoverableNavigation($request)) {
      return $this->responseHtml('misc/session_recovery.html.twig', status: 401)
        ->withHeader('Cache-Control', 'no-store')
        ->withHeader('Referrer-Policy', 'no-referrer')
        ->withHeader('X-Content-Type-Options', 'nosniff')
        ->withHeader('Content-Security-Policy', "default-src 'none'; script-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'")
        ->withHeader('Vary', 'Accept, Sec-Fetch-Mode, Sec-Fetch-Dest');
    }

    return self::responsePlain(401, "Ověření selhalo\n")
      ->withHeader('Cache-Control', 'no-store');
  }

  private function isRecoverableNavigation(ServerRequestInterface $request): bool
  {
    return $request->getMethod() === 'GET'
      && $request->getAttribute(Throwable::class) instanceof RecoverableSessionException
      && $request->getHeaderLine('Sec-Fetch-Mode') === 'navigate'
      && $request->getHeaderLine('Sec-Fetch-Dest') === 'document'
      && str_contains(strtolower($request->getHeaderLine('Accept')), 'text/html');
  }

  public function forbidden(ServerRequestInterface $request): ResponseInterface
  {
    $message = self::message($request);

    $this->logger->notice('Forbidden HTTP request', [
      'method' => $request->getMethod(),
      'path'   => $request->getUri()->getPath(),
      'reason' => $message
    ]);

    return self::responsePlain(403, 'Přístup odepřen: ' . $message . "\n")
      ->withHeader('Cache-Control', 'no-store');
  }

  public function tooManyAttempts(ServerRequestInterface $request): ResponseInterface
  {
    $message = self::message($request);

    return self::responsePlain(429, 'Příliš mnoho požadavků: ' . $message . "\n")
      ->withHeader('Cache-Control', 'no-store');
  }

  public function appError(ServerRequestInterface $request): ResponseInterface
  {
    $message = self::message($request);

    return self::responsePlain(409, 'Konflikt: ' . $message . "\n")
      ->withHeader('Cache-Control', 'no-store');
  }

  public function validationError(ServerRequestInterface $request): ResponseInterface
  {
    $invalid_parameters = $request
      ->getAttribute(Throwable::class)
      ->getErrors();

    return self::responseJson(422, compact('invalid_parameters'))
      ->withHeader('Cache-Control', 'no-store');
  }

  private static function message(ServerRequestInterface $request): string
  {
    return $request
      ->getAttribute(Throwable::class)
      ->getMessage();
  }
}
