<?php declare(strict_types=1);

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\User;
use App\Utilities\PasswordHasher;
use Illuminate\Database\Capsule\Manager as DB;

const APP_DIR = __DIR__ . '/..';

require APP_DIR . '/vendor/autoload.php';

$config = require APP_DIR . '/config/db.php';

if (($config['driver'] ?? null) !== 'mysql'
  || ! is_string($config['database'] ?? null)
  || ! str_ends_with($config['database'], '_test')
) {
  throw new RuntimeException(
    'Bruno seeding requires a MySQL database ending in _test.'
  );
}

$capsule = new DB();
$capsule->addConnection($config);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$emails = ['first@my.app', 'second@my.app'];

DB::connection()->transaction(function () use ($emails): void {
  if (User::query()->whereIn('email', $emails)->exists()) {
    throw new RuntimeException('Bruno test users already exist.');
  }

  $hasher = new PasswordHasher();
  $now = new DateTimeImmutable();

  $user = new User();
  $user->email = $emails[0];
  $user->role = UserRoleEnum::USER;
  $user->status = UserStatusEnum::ACTIVE;
  $user->register_date = $now;
  $user->password = $hasher->hash('firstuserpass');
  $user->save();

  $user2 = new User();
  $user2->email = $emails[1];
  $user2->role = UserRoleEnum::USER;
  $user2->status = UserStatusEnum::ACTIVE;
  $user2->register_date = $now;
  $user2->password = $hasher->hash('seconduserpass');
  $user2->save();
});

fwrite(STDOUT, "Seeded smoke-test users created.\n");
