<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Organization;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MemberGroup::class)]
class MemberGroupModelTest extends ModelTestAbstract
{
    public function test_it_belongs_to_an_organization(): void
    {
        // Arrange
        $organization = Organization::factory()->create();
        $group = MemberGroup::factory()->forOrganization($organization)->create();

        // Act
        $group->refresh();
        $organizationRel = $group->organization;

        // Assert
        $this->assertNotNull($organizationRel);
        $this->assertTrue($organizationRel->is($organization));
    }

    public function test_it_has_many_members_through_pivot(): void
    {
        // Arrange
        $organization = Organization::factory()->create();
        $group = MemberGroup::factory()->forOrganization($organization)->create();
        $memberA = Member::factory()->forOrganization($organization)->create();
        $memberB = Member::factory()->forOrganization($organization)->create();
        $group->members()->attach([$memberA->getKey(), $memberB->getKey()]);

        // Act
        $group->refresh();
        $members = $group->members;

        // Assert
        $this->assertCount(2, $members);
        $this->assertEqualsCanonicalizing(
            [$memberA->getKey(), $memberB->getKey()],
            $members->pluck('id')->all()
        );
    }

    public function test_member_has_groups_relation(): void
    {
        // Arrange
        $organization = Organization::factory()->create();
        $member = Member::factory()->forOrganization($organization)->create();
        $groupOne = MemberGroup::factory()->forOrganization($organization)->create();
        $groupTwo = MemberGroup::factory()->forOrganization($organization)->create();
        $member->groups()->attach([$groupOne->getKey(), $groupTwo->getKey()]);

        // Act
        $member->refresh();
        $groups = $member->groups;

        // Assert
        $this->assertCount(2, $groups);
        $this->assertEqualsCanonicalizing(
            [$groupOne->getKey(), $groupTwo->getKey()],
            $groups->pluck('id')->all()
        );
    }

    public function test_deleting_organization_cascades_to_member_groups(): void
    {
        // Arrange
        $organization = Organization::factory()->create();
        $group = MemberGroup::factory()->forOrganization($organization)->create();

        // Act
        $organization->delete();

        // Assert
        $this->assertDatabaseMissing('member_groups', ['id' => $group->getKey()]);
    }

    public function test_deleting_group_detaches_members(): void
    {
        // Arrange
        $organization = Organization::factory()->create();
        $member = Member::factory()->forOrganization($organization)->create();
        $group = MemberGroup::factory()->forOrganization($organization)->create();
        $group->members()->attach($member->getKey());

        // Act
        $group->delete();

        // Assert
        $this->assertDatabaseMissing('member_group_member', [
            'member_group_id' => $group->getKey(),
            'member_id' => $member->getKey(),
        ]);
        $this->assertDatabaseHas('members', ['id' => $member->getKey()]);
    }
}
