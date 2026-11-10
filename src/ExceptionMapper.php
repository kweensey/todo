<?php declare(strict_types=1);

use App\Controllers\ErrorController;
use App\Exceptions\BadRequestException;
use App\Exceptions\DomainStateException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\RecoverableSessionException;
use App\Exceptions\TooManyAttemptsException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationException;
use Oasys\Routing\ExceptionMapper;
use Oasys\Routing\NotFoundException as RoutingNotFound;

return (new ExceptionMapper([ErrorController::class, 'serverError']))
  ->register(RoutingNotFound::class, [ErrorController::class, 'notFound'])
  ->register(NotFoundException::class, [ErrorController::class, 'notFound'])
  ->register(BadRequestException::class, [ErrorController::class, 'badRequest'])
  ->register(UnauthorizedException::class, [ErrorController::class, 'authorizationError'])
  ->register(RecoverableSessionException::class, [ErrorController::class, 'authorizationError'])
  ->register(ForbiddenException::class, [ErrorController::class, 'forbidden'])
  ->register(TooManyAttemptsException::class, [ErrorController::class, 'tooManyAttempts'])
  ->register(DomainStateException::class, [ErrorController::class, 'appError'])
  ->register(ValidationException::class, [ErrorController::class, 'validationError']);
