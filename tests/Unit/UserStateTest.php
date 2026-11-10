<?php declare(strict_types=1);
namespace Tests\Unit;

use App\Enums\UserStatusEnum;
use App\Exceptions\DomainStateException;
use App\Models\User;
use PHPUnit\Framework\TestCase;

final class UserStateTest extends TestCase
{
    public function testOnlyDraftOrInvitedUsersCanBeDeleted(): void
    {
        $user = new User();
        $user->status = UserStatusEnum::DRAFT;
        self::assertTrue($user->isDeletable());
        $user->status = UserStatusEnum::INVITE;
        self::assertTrue($user->isDeletable());
        $user->status = UserStatusEnum::ACTIVE;
        self::assertFalse($user->isDeletable());
        $user->status = UserStatusEnum::BLOCKED;
        self::assertFalse($user->isDeletable());
    }

    public function testBlockingAndUnblockingRequireExpectedStatus(): void
    {
        $user = new User();
        $user->status = UserStatusEnum::ACTIVE;
        $user->assertBlockable();
        $user->block();
        self::assertTrue($user->isBlocked());
        $user->assertUnblockable();
        $user->unblock();
        self::assertTrue($user->isRegistered());
    }

    public function testUnblockingActiveUserIsRejected(): void
    {
        $user = new User();
        $user->status = UserStatusEnum::ACTIVE;
        $this->expectException(DomainStateException::class);
        $user->assertUnblockable();
    }
}
