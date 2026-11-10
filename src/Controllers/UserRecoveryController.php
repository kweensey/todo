<?php declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\HtmlResponse;
use App\Controllers\Concerns\PlainResponse;
use App\Controllers\Concerns\RequestData;
use App\Middlewares\TimingMiddleware as Timing;
use App\UseCases\User\Recovery\Password;
use App\UseCases\User\Recovery\PasswordOpen;
use App\UseCases\User\Recovery\Request;
use Oasys\Middleware\MiddlewareAttribute as Middleware;
use Oasys\Routing\Attributes\Get;
use Oasys\Routing\Attributes\Post;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment as Twig;

final class UserRecoveryController
{
  use RequestData;
  use PlainResponse;
  use HtmlResponse;

  public function __construct(
    private Request $request,
    private PasswordOpen $passwordOpen,
    private Password $password,
    private Twig $twig
  ) {}

  protected function template(): Twig
  {
    return $this->twig;
  }

  #[Middleware(Timing::class, min: 2000)]
  #[Post('/user/recover')]
  public function request(ServerRequestInterface $request): ResponseInterface
  {
    $input = self::getJsonPayload($request);

    ($this->request)($input);

    return self::responsePlain();
  }

  #[Post('/user/{uuid}/recover')]
  public function password(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $input = self::getJsonPayload($request);

    ($this->password)($uuid, $input);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Get('/user/recover')]
  public function emailPage(ServerRequestInterface $request): ResponseInterface
  {
    return $this->responseHtml('pages/user/recovery_email.html.twig');
  }

  #[Get('/user/{uuid}/recover')]
  public function passwordPage(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $query = $request->getQueryParams();

    $result = ($this->passwordOpen)($uuid, $query);

    $result['action'] = '/user/' . $uuid . '/recover';

    return $this->responseHtml('pages/user/recovery_password.html.twig', $result)
      ->withHeader('Cache-Control', 'no-store')
      ->withHeader('Referrer-Policy', 'no-referrer');
  }
}
