<?php declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\AuthenticatedUser;
use App\Controllers\Concerns\HtmlResponse;
use App\Controllers\Concerns\JsonResponse;
use App\Controllers\Concerns\PlainResponse;
use App\Controllers\Concerns\RequestData;
use App\Middlewares\AuthMiddleware as Auth;
use App\UseCases\Task\Browse;
use App\UseCases\Task\Complete;
use App\UseCases\Task\Create;
use App\UseCases\Task\CreateOpen;
use App\UseCases\Task\Delete as DeleteTask;
use App\UseCases\Task\Reopen;
use App\UseCases\Task\Update;
use App\UseCases\Task\UpdateOpen;
use Oasys\Middleware\MiddlewareAttribute as Middleware;
use Oasys\Routing\Attributes\Delete;
use Oasys\Routing\Attributes\Get;
use Oasys\Routing\Attributes\Post;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment as Twig;

#[Middleware(Auth::class)]
final class TaskController
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
    private Complete $complete,
    private Reopen $reopen,
    private DeleteTask $delete,
    private Twig $twig
  ) {}

  protected function template(): Twig
  {
    return $this->twig;
  }

  #[Get('/task')]
  public function listPage(ServerRequestInterface $request): ResponseInterface
  {
    $actor  = self::getAuthenticatedUser($request);
    $result = ($this->browse)($actor);

    return $this->responseHtml('pages/task/list.html.twig', $result)
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Get('/task/new')]
  public function createPage(ServerRequestInterface $request): ResponseInterface
  {
    $actor  = self::getAuthenticatedUser($request);
    $result = ($this->createOpen)($actor);

    return $this->responseHtml('pages/task/edit.html.twig', $result)
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Get('/task/{uuid}')]
  public function editPage(ServerRequestInterface $request): ResponseInterface
  {
    $uuid   = self::getUriParts($request)[1];
    $actor  = self::getAuthenticatedUser($request);
    $result = ($this->updateOpen)($uuid, $actor);

    return $this->responseHtml('pages/task/edit.html.twig', $result)
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Post('/task')]
  public function create(ServerRequestInterface $request): ResponseInterface
  {
    $input  = self::getJsonPayload($request);
    $actor  = self::getAuthenticatedUser($request);
    $result = ($this->create)($input, $actor);
    $uuid   = $result->getUuid();

    return self::responseJson(201, compact('uuid'))
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Post('/task/{uuid}')]
  public function update(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $input = self::getJsonPayload($request);
    $actor = self::getAuthenticatedUser($request);

    ($this->update)($uuid, $input, $actor);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Post('/task/{uuid}/complete')]
  public function complete(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $actor = self::getAuthenticatedUser($request);

    ($this->complete)($uuid, $actor);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Post('/task/{uuid}/reopen')]
  public function reopen(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $actor = self::getAuthenticatedUser($request);

    ($this->reopen)($uuid, $actor);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }

  #[Delete('/task/{uuid}')]
  public function delete(ServerRequestInterface $request): ResponseInterface
  {
    $uuid  = self::getUriParts($request)[1];
    $actor = self::getAuthenticatedUser($request);

    ($this->delete)($uuid, $actor);

    return self::responsePlain()
      ->withHeader('Cache-Control', 'no-store');
  }
}
