<?php declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\NotFoundException;
use App\Models\Task;
use App\UseCases\Task\Browse;
use App\UseCases\Task\Complete;
use App\UseCases\Task\Create;
use App\UseCases\Task\Delete;
use App\UseCases\Task\Reopen;
use App\UseCases\Task\Update;
use App\UseCases\Task\UpdateOpen;
use Oasys\Validation\Validator;
use Tests\Support\DatabaseTestCase;

final class TaskUseCasesTest extends DatabaseTestCase
{
    public function testCreatePersistsOwnerAndDoesNotTrustClientControlledFields(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();

        $task = (new Create(new Validator(patterns: [])))([
            'title' => 'My new task',
            'user_id' => $other->getKey(),
            'completed' => true,
        ], $owner);

        $persisted = Task::query()->findOrFail($task->getKey());
        self::assertSame($owner->getKey(), $persisted->user_id);
        self::assertSame('My new task', $persisted->title);
        self::assertFalse($persisted->isCompleted());
        self::assertMatchesRegularExpression(
            '/^[A-F0-9]{8}(?:-[A-F0-9]{4}){3}-[A-F0-9]{12}$/',
            $persisted->getUuid()
        );
    }

    public function testUpdateOnlyChangesTitleAndPersistsIt(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        $task = $this->createTask($owner);

        (new Update(new Validator(patterns: [])))($task->getUuid(), [
            'title' => 'Renamed',
            'user_id' => $other->getKey(),
            'completed' => true,
        ], $owner);

        $task->refresh();
        self::assertSame('Renamed', $task->title);
        self::assertSame($owner->getKey(), $task->user_id);
        self::assertFalse($task->isCompleted());
    }

    public function testCompleteReopenAndDeletePersist(): void
    {
        $owner = $this->createUser();
        $task = $this->createTask($owner);
        $uuid = $task->getUuid();

        (new Complete())($uuid, $owner);
        self::assertTrue($task->fresh()->isCompleted());

        (new Reopen())($uuid, $owner);
        self::assertFalse($task->fresh()->isCompleted());

        (new Delete())($uuid, $owner);
        self::assertNull($task->fresh());

        $this->expectException(NotFoundException::class);
        (new Complete())($uuid, $owner);
    }

    public function testBrowseShowsOnlyActorsTasks(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        $mine = $this->createTask($owner, 'Mine');
        $this->createTask($other, 'Private');

        $view = (new Browse())($owner);

        self::assertSame($owner, $view['actor']);
        self::assertSame(1, $view['count']);
        self::assertSame([$mine->getUuid()], array_map(
            static fn (Task $task): string => $task->getUuid(),
            $view['items']->all()
        ));
    }

    public function testOtherUsersCannotReadEditOrMutateTask(): void
    {
        $owner = $this->createUser();
        $intruder = $this->createUser();
        $task = $this->createTask($owner, 'Private task');
        $uuid = $task->getUuid();

        $attempts = [
            'open edit page' => static fn () => (new UpdateOpen())($uuid, $intruder),
            'update' => static fn () => (new Update(new Validator(patterns: [])))($uuid, ['title' => 'Stolen'], $intruder),
            'complete' => static fn () => (new Complete())($uuid, $intruder),
            'reopen' => static fn () => (new Reopen())($uuid, $intruder),
            'delete' => static fn () => (new Delete())($uuid, $intruder),
        ];

        foreach ($attempts as $operation => $attempt) {
            try {
                $attempt();
                self::fail("Expected NotFoundException for {$operation}");
            } catch (NotFoundException) {
                // continue
            }
        }

        $task->refresh();
        self::assertSame('Private task', $task->title);
        self::assertFalse($task->isCompleted());
        self::assertSame($owner->getKey(), $task->user_id);
    }
}
