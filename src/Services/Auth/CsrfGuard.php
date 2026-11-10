<?php declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\ForbiddenException;
use Oasys\Config\ArrayConfig as Config;
use Psr\Http\Message\ServerRequestInterface;

final class CsrfGuard
{
  public const COOKIE_NAME = '__Host-csrf_token';

  private const HEADER_NAME = 'X-CSRF-Token';

  public function __construct(
    private Config $config
  ) {}

  public function generate(): string
  {
    return bin2hex(random_bytes(32));
  }

  public function assertOrigin(ServerRequestInterface $request): void
  {
    $origin = rtrim($this->config->get('app.host'), '/');

    $request->getHeaderLine('Origin') === $origin
      || throw new ForbiddenException('Invalid origin');
  }

  public function assertValid(ServerRequestInterface $request): void
  {
    $this->assertOrigin($request);

    $csrfCookie = $request->getCookieParams()[self::COOKIE_NAME] ?? null;
    $csrfHeader = $request->getHeaderLine(self::HEADER_NAME);

    is_string($csrfCookie)
      && preg_match('/\A[a-f0-9]{64}\z/', $csrfCookie) === 1
      && hash_equals($csrfCookie, $csrfHeader)
      || throw new ForbiddenException('Invalid CSRF token');
  }
}
