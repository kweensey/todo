<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UserRoleEnum;
use App\Exceptions\ForbiddenException;
use App\Models\User;
use App\Policies\UserPolicy;
use PHPUnit\Framework\TestCase;

final class UserPolicyTest extends TestCase
{
    public function testUserAdministrationIsRestrictedToAdmins(): void
    {
        $policy = new UserPolicy();
        $admin = new User();
        $admin->role = UserRoleEnum::ADMIN;
        $regular = new User();
        $regular->role = UserRoleEnum::USER;

        foreach (['Browse', 'Create', 'Update', 'Invite', 'Block', 'Unblock', 'Delete'] as $action) {
            $can = 'can' . $action;
            self::assertTrue($policy->{$can}($admin), "Admin should {$action}");
            self::assertFalse($policy->{$can}($regular), "Regular user should not {$action}");
        }

        $policy->assertCanCreate($admin);
        $this->expectException(ForbiddenException::class);
        $policy->assertCanCreate($regular);
    }

    public function testAdminCannotBlockSelfOrTheirCreator(): void
    {
        $policy = new UserPolicy();
        $actor = new User();
        $actor->id = '00000000-0000-4000-8000-000000000010';

        $creator = new User();
        $creator->id = '00000000-0000-4000-8000-000000000020';
        $actor->created_by_id = $creator->getKey();

        $other = new User();
        $other->id = '00000000-0000-4000-8000-000000000030';

        self::assertFalse($policy->canBlockTarget($actor, $actor));
        self::assertFalse($policy->canBlockTarget($actor, $creator));
        self::assertTrue($policy->canBlockTarget($actor, $other));

        $this->expectException(ForbiddenException::class);
        $policy->assertCanBlockTarget($actor, $creator);
    }
}
