<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ValidationException;
use App\Models\User;
use App\UseCases\Task\Create;
use App\UseCases\Task\Update;
use Oasys\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaskInputValidationTest extends TestCase
{
    public static function invalidTitles(): iterable
    {
        yield 'missing' => [[]];
        yield 'null' => [['title' => null]];
        yield 'numeric' => [['title' => 42]];
        yield 'too long' => [['title' => str_repeat('x', 256)]];
    }

    #[DataProvider('invalidTitles')]
    public function testCreateRejectsInvalidTitleBeforeWriting(array $input): void
    {
        try {
            (new Create(new Validator(patterns: [])))($input, new User());
            self::fail('Create must reject an invalid title');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('title', $exception->getErrors());
        }
    }

    #[DataProvider('invalidTitles')]
    public function testUpdateRejectsInvalidTitleBeforeLookingUpTask(array $input): void
    {
        try {
            (new Update(new Validator(patterns: [])))('not-a-uuid', $input, new User());
            self::fail('Update must reject an invalid title');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('title', $exception->getErrors());
        }
    }
}
