<?php declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\AuthenticatedUser;
use App\Controllers\Concerns\HtmlResponse;
use App\Controllers\Concerns\JsonResponse;
use App\Controllers\Concerns\PlainResponse;
use App\Controllers\Concerns\RequestData;
use App\Middlewares\AuthMiddleware as Auth;
use App\UseCases\User\Block;
use App\UseCases\User\Browse;
use App\UseCases\User\Create;
use App\UseCases\User\CreateOpen;
use App\UseCases\User\Delete as DeleteUser;
use App\UseCases\User\Invite;
use App\UseCases\User\Unblock;
use App\UseCases\User\Update;
use App\UseCases\User\UpdateOpen;
use Oasys\Middleware\MiddlewareAttribute as Middleware;
use Oasys\Routing\Attributes\Delete;
use Oasys\Routing\Attributes\Get;
use Oasys\Routing\Attributes\Post;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment as Twig;

final class UserController
{
  use AuthenticatedUser;
  use HtmlResponse;
  use JsonResponse;
  use PlainResponse;
  use RequestData;

  public function __construct(
    private Browse $browse,
    private CreateOpen $createOpen,
    private Create $create,
    private UpdateOpen $updateOpen,
    private Update $update,
    private DeleteUser $delete,
    private Invite $invite,
    private Block $block,
    private Unblock $unblock,
    private Twig $twig
  ) {}

  protected function template(): Twig
  {
    return $this->twig;
  }

  #[Middleware(Auth::class)]
  #[Post('/user')]
  public function create(ServerRequestInterface $request): ResponseInterface
  {
    $input = self::getJsonPayload($request);
    $actor = self::getAuthenticatedUser($request);

    $result = ($this->create)($input, $actor);

    $uuid = $result->getUuid();

    return self::responseJson(201, compact('uuid'))
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Middleware(Auth::class)]
  #[Post('/user/{uuid}')]
  public function update(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $input = self::getJsonPayload($request);
    $actor = self::getAuthenticatedUser($request);

    ($this->update)($uuid, $input, $actor);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Middleware(Auth::class)]
  #[Delete('/user/{uuid}')]
  public function delete(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $actor = self::getAuthenticatedUser($request);

    ($this->delete)($uuid, $actor);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Middleware(Auth::class)]
  #[Post('/user/{uuid}/invite')]
  public function invite(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $actor = self::getAuthenticatedUser($request);

    ($this->invite)($uuid, $actor);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Middleware(Auth::class)]
  #[Post('/user/{uuid}/block')]
  public function block(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $actor = self::getAuthenticatedUser($request);

    ($this->block)($uuid, $actor);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Middleware(Auth::class)]
  #[Post('/user/{uuid}/unblock')]
  public function unblock(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $actor = self::getAuthenticatedUser($request);

    ($this->unblock)($uuid, $actor);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Get('/user/login')]
  public function loginPage(ServerRequestInterface $request): ResponseInterface
  {
    return $this->responseHtml('pages/user/login.html.twig');
  }

  #[Middleware(Auth::class)]
  #[Get('/user')]
  public function listPage(ServerRequestInterface $request): ResponseInterface
  {
    $actor = self::getAuthenticatedUser($request);

    $result = ($this->browse)($actor);

    return $this->responseHtml('pages/user/list.html.twig', $result)
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Middleware(Auth::class)]
  #[Get('/user/new')]
  public function createPage(ServerRequestInterface $request): ResponseInterface
  {
    $actor = self::getAuthenticatedUser($request);

    $result = ($this->createOpen)($actor);

    return $this->responseHtml('pages/user/edit.html.twig', $result)
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Middleware(Auth::class)]
  #[Get('/user/{uuid}')]
  public function editPage(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $actor = self::getAuthenticatedUser($request);

    $result = ($this->updateOpen)($uuid, $actor);

    return $this->responseHtml('pages/user/edit.html.twig', $result)
      ->withHeader('Cache-Control', 'no-store');
  }
}
