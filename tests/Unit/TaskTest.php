<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Task;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function testCompleteAndReopenSetStateIdempotently(): void
    {
        $task = new Task();
        $task->completed = false;

        self::assertFalse($task->isCompleted());
        self::assertSame($task, $task->complete());
        self::assertSame($task, $task->complete());
        self::assertTrue($task->isCompleted());
        self::assertSame($task, $task->reopen());
        self::assertSame($task, $task->reopen());
        self::assertFalse($task->isCompleted());
    }
}
