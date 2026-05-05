<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Organization;
use App\Service\TimeEntryMemberFilterResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(TimeEntryMemberFilterResolver::class)]
class TimeEntryMemberFilterResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_no_member_or_group_filters(): void
    {
        $organization = Organization::factory()->create();

        $this->assertNull(TimeEntryMemberFilterResolver::resolveForOrganization($organization, null, null));
        $this->assertNull(TimeEntryMemberFilterResolver::resolveForOrganization($organization, [], []));
    }

    public function test_returns_explicit_member_ids_only(): void
    {
        $organization = Organization::factory()->create();
        $member = Member::factory()->forOrganization($organization)->create();

        $resolved = TimeEntryMemberFilterResolver::resolveForOrganization($organization, [$member->getKey()], null);

        $this->assertSame([$member->getKey()], $resolved);
    }

    public function test_expands_member_groups_and_unions_with_explicit_ids(): void
    {
        $organization = Organization::factory()->create();
        $memberInGroup = Member::factory()->forOrganization($organization)->create();
        $memberExplicit = Member::factory()->forOrganization($organization)->create();
        Member::factory()->forOrganization($organization)->create();

        $group = MemberGroup::factory()->forOrganization($organization)->create();
        $group->members()->sync([$memberInGroup->getKey()]);

        $resolved = TimeEntryMemberFilterResolver::resolveForOrganization(
            $organization,
            [$memberExplicit->getKey()],
            [$group->getKey()]
        );

        $this->assertCount(2, $resolved);
        $this->assertContains($memberInGroup->getKey(), $resolved);
        $this->assertContains($memberExplicit->getKey(), $resolved);
    }

    public function test_empty_group_with_no_explicit_members_yields_empty_array(): void
    {
        $organization = Organization::factory()->create();
        $group = MemberGroup::factory()->forOrganization($organization)->create();

        $resolved = TimeEntryMemberFilterResolver::resolveForOrganization($organization, null, [$group->getKey()]);

        $this->assertSame([], $resolved);
    }
}
