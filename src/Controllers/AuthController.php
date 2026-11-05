<?php declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\CookieHeader;
use App\Controllers\Concerns\JsonResponse;
use App\Controllers\Concerns\PlainResponse;
use App\Controllers\Concerns\RequestData;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use App\Middlewares\AuthMiddleware as Auth;
use App\Services\Auth\CsrfGuard;
use App\UseCases\Auth\Access;
use App\UseCases\Auth\Refresh;
use App\UseCases\Auth\Revoke;
use Oasys\Middleware\MiddlewareAttribute as Middleware;
use Oasys\Routing\Attributes\Delete;
use Oasys\Routing\Attributes\Get;
use Oasys\Routing\Attributes\Post;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
  use RequestData;
  use PlainResponse;
  use JsonResponse;
  use CookieHeader;

  private const REFRESH_COOKIE_PATH = '/auth/session';

  public function __construct(
    private Access $access,
    private Refresh $refresh,
    private Revoke $revoke,
    private CsrfGuard $csrf
  ) {}

  #[Post('/auth/access')]
  public function access(ServerRequestInterface $request): ResponseInterface
  {
    $input = self::getJsonPayload($request);
    
    $result = ($this->access)($input);

    return self::responseJson(200, $result)
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Post('/auth/revoke')]
  public function revoke(ServerRequestInterface $request): ResponseInterface
  {
    $input = self::getJsonPayload($request);

    ($this->revoke)($input);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Post('/auth/session')]
  public function session(ServerRequestInterface $request): ResponseInterface
  {
    $this->csrf->assertOrigin($request);

    $input = self::getJsonPayload($request);
    
    $result = ($this->access)($input);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store')
      ->withAddedHeader('Set-Cookie', [
        self::cookie('__Host-access_token', $result['access_token'], '/', httpOnly: true),
        self::cookie('refresh_token', $result['refresh_token'], self::REFRESH_COOKIE_PATH, httpOnly: true),
        self::cookie(CsrfGuard::COOKIE_NAME, $this->csrf->generate(), '/', httpOnly: false)
      ]);
  }

  #[Middleware(Auth::class)]
  #[Get('/auth/session/check')]
  public function sessionCheck(ServerRequestInterface $request): ResponseInterface
  {
    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Delete('/auth/session')]
  public function sessionDelete(ServerRequestInterface $request): ResponseInterface
  {
    $this->csrf->assertValid($request);

    $cookies = $request->getCookieParams();

    if (isset($cookies['refresh_token'])) {
      try {
        ($this->revoke)([
          'refresh_token' => $cookies['refresh_token']
        ]);
      } catch (UnauthorizedException | ValidationException) {
        // continue
      }
    }

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store')
      ->withAddedHeader('Set-Cookie', [
        self::cookie('__Host-access_token', '', '/', httpOnly: true, expired: true),
        self::cookie('refresh_token', '', self::REFRESH_COOKIE_PATH, httpOnly: true, expired: true),
        self::cookie(CsrfGuard::COOKIE_NAME, '', '/', httpOnly: false, expired: true)
      ]);
  }

  #[Post('/auth/refresh')]
  public function refresh(ServerRequestInterface $request): ResponseInterface
  {
    $input = self::getJsonPayload($request);
    
    $result = ($this->refresh)($input);

    return self::responseJson(200, $result)
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Post('/auth/session/refresh')]
  public function sessionRefresh(ServerRequestInterface $request): ResponseInterface
  {
    $this->csrf->assertValid($request);
    
    $cookies = $request->getCookieParams();

    $result = ($this->refresh)([
      'refresh_token' => $cookies['refresh_token'] ?? null
    ]);

    return self::responsePlain(204)
      ->withHeader('Cache-Control', 'no-store')
      ->withAddedHeader('Set-Cookie', [
        self::cookie('__Host-access_token', $result['access_token'], '/', httpOnly: true),
        self::cookie('refresh_token', $result['refresh_token'], self::REFRESH_COOKIE_PATH, httpOnly: true)
      ]);
  }
}
