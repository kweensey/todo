<?php declare(strict_types=1);

use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Database\Capsule\Manager;
use Oasys\Kernel;

$capsule = new Manager();

$capsule->addConnection(require APP_DIR . '/config/db.php');
$capsule->setAsGlobal();
$capsule->bootEloquent();

$kernel = new Kernel(
  require __DIR__ . '/Container.php',
  require __DIR__ . '/Router.php',
  require __DIR__ . '/ExceptionMapper.php'
);

$request = ServerRequest::fromGlobals();

$response = $kernel->resolve($request);

Kernel::send($response);
