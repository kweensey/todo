<?php declare(strict_types=1);

use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Utilities\PasswordHasher;
use Illuminate\Database\Capsule\Manager;

if (PHP_SAPI !== 'cli') {
  exit(1);
}

const APP_DIR = __DIR__ . '/..';

require APP_DIR . '/vendor/autoload.php';

$email = $argv[1] ?? '';

if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 128) {
  fwrite(STDERR, "Usage: php scripts/bootstrap-admin.php admin@example.com\n");

  exit(1);
}

$capsule = new Manager();
$capsule->addConnection(require APP_DIR . '/config/db.php');
$capsule->setAsGlobal();
$capsule->bootEloquent();

if (User::withTrashed()->exists()) {
  fwrite(STDERR, "Refusing to bootstrap: users already exist.\n");

  exit(1);
}

$password = bin2hex(random_bytes(24));

$user = new User();
$user->email = $email;
$user->role = UserRoleEnum::ADMIN;

$user->register((new PasswordHasher())->hash($password), new DateTimeImmutable());
$user->save();

fwrite(STDOUT, "Administrator created: {$email}\n");
fwrite(STDOUT, "One-time initial password: {$password}\n");
fwrite(STDOUT, "Store it securely and change it after the first login.\n");
