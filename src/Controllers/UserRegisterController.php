<?php declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\HtmlResponse;
use App\Controllers\Concerns\PlainResponse;
use App\Controllers\Concerns\RequestData;
use App\UseCases\User\Register\EmailConfirm;
use App\UseCases\User\Register\EmailConfirmOpen;
use App\UseCases\User\Register\EmailOpen;
use App\UseCases\User\Register\EmailRequest;
use App\UseCases\User\Register\Password;
use App\UseCases\User\Register\PasswordOpen;
use Oasys\Routing\Attributes\Get;
use Oasys\Routing\Attributes\Post;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment as Twig;

final class UserRegisterController
{
  use RequestData;
  use PlainResponse;
  use HtmlResponse;

  public function __construct(
    private EmailOpen $emailOpen,
    private EmailRequest $emailRequest,
    private EmailConfirmOpen $emailConfirmOpen,
    private EmailConfirm $emailConfirm,
    private Password $password,
    private PasswordOpen $passwordOpen,
    private Twig $twig
  ) {}

  protected function template(): Twig
  {
    return $this->twig;
  }

  #[Post('/user/{uuid}/register/email')]
  public function email(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $input = self::getJsonPayload($request);

    ($this->emailRequest)($uuid, $input);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Post('/user/{uuid}/register/email/confirm')]
  public function emailConfirm(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $input = self::getJsonPayload($request);

    ($this->emailConfirm)($uuid, $input);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Post('/user/{uuid}/register/password')]
  public function password(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $input = self::getJsonPayload($request);

    ($this->password)($uuid, $input);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Get('/user/{uuid}/register')]
  public function emailPage(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $query = $request->getQueryParams();

    $result = ($this->emailOpen)($uuid, $query);

    return $this->responseHtml('pages/user/register_email.html.twig', $result)
      ->withHeader('Cache-Control', 'no-store')
      ->withHeader('Referrer-Policy', 'no-referrer');
  }

  #[Get('/user/{uuid}/register/confirm')]
  public function emailConfirmPage(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $query = $request->getQueryParams();

    $result = ($this->emailConfirmOpen)($uuid, $query);

    $result['action'] = '/user/' . $uuid . '/register/email/confirm';

    return $this->responseHtml('modal/challenge.html.twig', $result)
      ->withHeader('Cache-Control', 'no-store')
      ->withHeader('Referrer-Policy', 'no-referrer');
  }

  #[Get('/user/{uuid}/register/password')]
  public function passwordPage(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $query = $request->getQueryParams();

    $result = ($this->passwordOpen)($uuid, $query);

    return $this->responseHtml('pages/user/register_password.html.twig', $result)
      ->withHeader('Cache-Control', 'no-store')
      ->withHeader('Referrer-Policy', 'no-referrer');
  }
}
