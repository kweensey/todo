<?php declare(strict_types=1);

namespace Tests\Support;

use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class DatabaseTestCase extends TestCase
{
    const APP_DIR = __DIR__ . '/../..';

    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require self::APP_DIR . '/config/db.php';

        if (($config['driver'] ?? null) !== 'mysql'
            || ! is_string($config['database'] ?? null)
            || ! str_ends_with($config['database'], '_test')
        ) {
            throw new RuntimeException(
                'Integration tests require a MySQL database ending in _test.'
            );
        }

        $capsule = new DB();
        $capsule->addConnection($config);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        DB::connection()->beginTransaction();

        $this->transactionStarted = true;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->transactionStarted) {
                DB::connection()->rollBack();
                $this->transactionStarted = false;
            }
        } finally {
            parent::tearDown();
        }
    }

    protected function createUser(UserRoleEnum $role = UserRoleEnum::USER): User
    {
        $user = new User();
        $user->email = 'phpunit-' . bin2hex(random_bytes(12)) . '@example.test';
        $user->role = $role;
        $user->status = UserStatusEnum::ACTIVE;
        $user->save();

        return $user;
    }

    protected function createTask(User $owner, string $title = 'Owned task'): Task
    {
        $task = new Task();
        $task->title = $title;
        $task->completed = false;
        $task->user_id = $owner->getKey();
        $task->save();

        return $task;
    }
}
