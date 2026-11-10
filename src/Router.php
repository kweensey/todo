<?php declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\TaskController;
use App\Controllers\UserController;
use App\Controllers\UserChangeController;
use App\Controllers\UserRecoveryController;
use App\Controllers\UserRegisterController;
use Oasys\Routing\Router;

$router = new Router(aliases: [
  'uuid' => '[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}'
]);

return $router
  ->get('/', [UserController::class, 'loginPage'])
  ->bind(AuthController::class)
  ->bind(UserController::class)
  ->bind(UserChangeController::class)
  ->bind(UserRecoveryController::class)
  ->bind(UserRegisterController::class)
  ->bind(TaskController::class);
