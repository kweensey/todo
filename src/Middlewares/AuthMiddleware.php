<?php declare(strict_types=1);

namespace App\Middlewares;

use App\Exceptions\RecoverableSessionException;
use App\Exceptions\UnauthorizedException;
use App\Models\User;
use App\Services\Auth\CsrfGuard;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Oasys\Config\ArrayConfig as Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use DomainException;
use OpenSSLAsymmetricKey;
use RuntimeException;
use stdClass;
use UnexpectedValueException;

final class AuthMiddleware implements MiddlewareInterface
{
  public function __construct(
    private Config $config,
    private CsrfGuard $csrf
  ) {}

  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
  {
    $accessToken = $this->extractAccessToken($request);
    $cookieAuth  = ! $request->hasHeader('Authorization');
    $token       = $this->decodeToken($accessToken, $cookieAuth);

    $this->validate($token);

    $user    = $this->loadUser($token->sub);
    $request = $request->withAttribute(User::class, $user);

    return $handler->handle($request);
  }

  private function extractAccessToken(ServerRequestInterface $request): string
  {
    if ($request->hasHeader('Authorization')) {
      $authorization = $request->getHeaderLine('Authorization');

      preg_match('/\ABearer[ \t]+(\S+)\z/i', $authorization, $matches)
        || throw new UnauthorizedException('Invalid authorization header');

      return $matches[1];
    }

    $accessToken = $request->getCookieParams()['__Host-access_token']
      ?? null;

    is_string($accessToken) && $accessToken !== ''
      || throw new RecoverableSessionException('Missing authorization token');

    in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)
      || $this->csrf->assertValid($request);

    return $accessToken;
  }

  private function decodeToken(string $accessToken, bool $cookieAuth): stdClass
  {
    $publicKey = $this->loadPublicKey();

    try {
      return JWT::decode(
        $accessToken,
        new Key($publicKey, 'RS256')
      );
    } catch (DomainException | UnexpectedValueException | ExpiredException $exception) {
      $exception instanceof ExpiredException && $cookieAuth
        ? throw new RecoverableSessionException('Expired access token')
        : throw new UnauthorizedException('Cannot authenticate');
    }
  }

  private function loadPublicKey(): OpenSSLAsymmetricKey
  {
    $pem = @file_get_contents(APP_DIR . '/cert/public.pem');

    $pem !== false
      && $pem !== ''
      || throw new RuntimeException('JWT public key unavailable');

    $key = @openssl_pkey_get_public($pem);

    $key === false
      && throw new RuntimeException('JWT public key invalid');

    $details = openssl_pkey_get_details($key);

    $details !== false
      && $details['type'] === OPENSSL_KEYTYPE_RSA
      && $details['bits'] >= 2048
      || throw new RuntimeException('JWT public key must be RSA 2048+');

    return $key;
  }

  private function validate(stdClass $token): void
  {
    is_string($token->sub ?? null)
      && $token->sub !== ''
      && ($token->iss ?? null) === $this->config->get('app.host')
      && ($token->aud ?? null) === $this->config->get('app.appId')
      && is_int($token->iat ?? null)
      && is_int($token->exp ?? null)
      && is_string($token->jti ?? null)
      && $token->jti !== ''
      || throw new UnauthorizedException('Cannot authenticate');
  }

  private function loadUser(string $uuid): User
  {
    return User::query()
      ->where('uuid', $uuid)
      ->registered()
      ->first()
      ?? throw new UnauthorizedException('Cannot authenticate');
  }
}
