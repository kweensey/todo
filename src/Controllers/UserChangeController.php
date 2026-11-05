<?php declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\AuthenticatedUser;
use App\Controllers\Concerns\CookieHeader;
use App\Controllers\Concerns\HtmlResponse;
use App\Controllers\Concerns\PlainResponse;
use App\Controllers\Concerns\RequestData;
use App\Middlewares\AuthMiddleware as Auth;
use App\Services\Auth\CsrfGuard;
use App\UseCases\User\Change\EmailConfirm;
use App\UseCases\User\Change\EmailConfirmOpen;
use App\UseCases\User\Change\EmailRequest;
use App\UseCases\User\Change\Password;
use Oasys\Middleware\MiddlewareAttribute as Middleware;
use Oasys\Routing\Attributes\Get;
use Oasys\Routing\Attributes\Post;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment as Twig;

#[Middleware(Auth::class)]
final class UserChangeController
{
  use AuthenticatedUser;
  use CookieHeader;
  use HtmlResponse;
  use PlainResponse;
  use RequestData;

  private const REFRESH_COOKIE_PATH = '/auth/session';

  public function __construct(
    private EmailRequest $emailRequest,
    private EmailConfirm $emailConfirm,
    private EmailConfirmOpen $emailConfirmOpen,
    private Password $password,
    private Twig $twig
  ) {}

  protected function template(): Twig
  {
    return $this->twig;
  }

  #[Post('/user/me/change/email')]
  public function emailRequest(ServerRequestInterface $request): ResponseInterface
  {
    $input = self::getJsonPayload($request);
    $actor = self::getAuthenticatedUser($request);

    ($this->emailRequest)($input, $actor);

    return self::responsePlain();
  }

  #[Post('/user/me/change/email/confirm')]
  public function emailConfirm(ServerRequestInterface $request): ResponseInterface
  {
    $input = self::getJsonPayload($request);
    $actor = self::getAuthenticatedUser($request);

    ($this->emailConfirm)($input, $actor);

    return self::responsePlain();
  }

  #[Post('/user/me/change/password')]
  public function password(ServerRequestInterface $request): ResponseInterface
  {
    $input = self::getJsonPayload($request);
    $actor = self::getAuthenticatedUser($request);

    ($this->password)($input, $actor);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store')
      ->withAddedHeader('Set-Cookie', [
        self::cookie('__Host-access_token', '', '/', httpOnly: true, expired: true),
        self::cookie('refresh_token', '', self::REFRESH_COOKIE_PATH, httpOnly: true, expired: true),
        self::cookie(CsrfGuard::COOKIE_NAME, '', '/', httpOnly: false, expired: true)
      ]);
  }

  #[Get('/user/me/change/email')]
  public function emailPage(ServerRequestInterface $request): ResponseInterface
  {
    $actor = self::getAuthenticatedUser($request);

    return $this->responseHtml('pages/user/change_email.html.twig', compact('actor'))
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Get('/user/me/change/email/confirm')]
  public function emailConfirmPage(ServerRequestInterface $request): ResponseInterface
  {
    $actor = self::getAuthenticatedUser($request);

    $result = ($this->emailConfirmOpen)($actor);

    $result['action'] = '/user/me/change/email/confirm';

    return $this->responseHtml('modal/challenge.html.twig', $result)
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Get('/user/me/change/password')]
  public function passwordPage(ServerRequestInterface $request): ResponseInterface
  {
    $actor = self::getAuthenticatedUser($request);

    return $this->responseHtml('pages/user/change_password.html.twig', compact('actor'))
      ->withHeader('Cache-Control', 'no-store');
  }
}
