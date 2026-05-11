<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Api\V1;

use App\Http\Controllers\Api\V1\MemberGroupController;
use App\Models\Member;
use App\Models\MemberGroup;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(MemberGroupController::class)]
class MemberGroupEndpointTest extends ApiEndpointTestAbstract
{
    public function test_index_fails_if_user_has_no_permission_to_view_members(): void
    {
        // Arrange
        $data = $this->createUserWithPermission();
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.member-groups.index', $data->organization->getKey()));

        // Assert
        $response->assertStatus(403);
    }

    public function test_index_returns_groups_of_organization_only(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['members:view']);
        $groupInOrg = MemberGroup::factory()->forOrganization($data->organization)->create();
        $otherData = $this->createUserWithPermission(['members:view']);
        MemberGroup::factory()->forOrganization($otherData->organization)->create();
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.member-groups.index', $data->organization->getKey()));

        // Assert
        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($groupInOrg->getKey(), $ids);
        $this->assertCount(1, $ids);
    }

    public function test_store_fails_if_user_has_no_permission_to_update_members(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['members:view']);
        Passport::actingAs($data->user);

        // Act
        $response = $this->postJson(route('api.v1.member-groups.store', $data->organization->getKey()), [
            'name' => 'Engineering',
        ]);

        // Assert
        $response->assertStatus(403);
    }

    public function test_store_creates_member_group(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['members:update']);
        Passport::actingAs($data->user);

        // Act
        $response = $this->postJson(route('api.v1.member-groups.store', $data->organization->getKey()), [
            'name' => 'Engineering',
        ]);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('member_groups', [
            'organization_id' => $data->organization->getKey(),
            'name' => 'Engineering',
        ]);
    }

    public function test_store_fails_when_name_already_exists_in_organization(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['members:update']);
        MemberGroup::factory()->forOrganization($data->organization)->create([
            'name' => 'Engineering',
        ]);
        Passport::actingAs($data->user);

        // Act
        $response = $this->postJson(route('api.v1.member-groups.store', $data->organization->getKey()), [
            'name' => 'Engineering',
        ]);

        // Assert
        $response->assertStatus(422);
    }

    public function test_update_renames_member_group(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['members:update']);
        $group = MemberGroup::factory()->forOrganization($data->organization)->create([
            'name' => 'Old',
        ]);
        Passport::actingAs($data->user);

        // Act
        $response = $this->putJson(
            route('api.v1.member-groups.update', [$data->organization->getKey(), $group->getKey()]),
            ['name' => 'New']
        );

        // Assert
        $response->assertStatus(200);
        $group->refresh();
        $this->assertSame('New', $group->name);
    }

    public function test_update_fails_when_group_belongs_to_different_organization(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['members:update']);
        $otherData = $this->createUserWithPermission(['members:update']);
        $foreignGroup = MemberGroup::factory()->forOrganization($otherData->organization)->create();
        Passport::actingAs($data->user);

        // Act
        $response = $this->putJson(
            route('api.v1.member-groups.update', [$data->organization->getKey(), $foreignGroup->getKey()]),
            ['name' => 'Hijack']
        );

        // Assert
        $response->assertStatus(403);
    }

    public function test_destroy_deletes_member_group(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['members:update']);
        $group = MemberGroup::factory()->forOrganization($data->organization)->create();
        Passport::actingAs($data->user);

        // Act
        $response = $this->deleteJson(
            route('api.v1.member-groups.destroy', [$data->organization->getKey(), $group->getKey()])
        );

        // Assert
        $response->assertStatus(204);
        $this->assertDatabaseMissing('member_groups', ['id' => $group->getKey()]);
    }

    public function test_sync_members_replaces_group_membership(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['members:update']);
        $group = MemberGroup::factory()->forOrganization($data->organization)->create();
        $memberOne = Member::factory()->forOrganization($data->organization)->create();
        $memberTwo = Member::factory()->forOrganization($data->organization)->create();
        $memberThree = Member::factory()->forOrganization($data->organization)->create();
        $group->members()->attach($memberOne->getKey());
        Passport::actingAs($data->user);

        // Act
        $response = $this->putJson(
            route('api.v1.member-groups.sync-members', [$data->organization->getKey(), $group->getKey()]),
            ['member_ids' => [$memberTwo->getKey(), $memberThree->getKey()]]
        );

        // Assert
        $response->assertStatus(200);
        $group->refresh();
        $ids = $group->members->pluck('id')->all();
        $this->assertCount(2, $ids);
        $this->assertEqualsCanonicalizing(
            [$memberTwo->getKey(), $memberThree->getKey()],
            $ids
        );
    }

    public function test_sync_members_rejects_member_from_other_organization(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['members:update']);
        $group = MemberGroup::factory()->forOrganization($data->organization)->create();
        $otherData = $this->createUserWithPermission(['members:update']);
        $foreignMember = Member::factory()->forOrganization($otherData->organization)->create();
        Passport::actingAs($data->user);

        // Act
        $response = $this->putJson(
            route('api.v1.member-groups.sync-members', [$data->organization->getKey(), $group->getKey()]),
            ['member_ids' => [$foreignMember->getKey()]]
        );

        // Assert
        $response->assertStatus(422);
    }

    public function test_sync_members_with_empty_array_clears_group(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['members:update']);
        $group = MemberGroup::factory()->forOrganization($data->organization)->create();
        $member = Member::factory()->forOrganization($data->organization)->create();
        $group->members()->attach($member->getKey());
        Passport::actingAs($data->user);

        // Act
        $response = $this->putJson(
            route('api.v1.member-groups.sync-members', [$data->organization->getKey(), $group->getKey()]),
            ['member_ids' => []]
        );

        // Assert
        $response->assertStatus(200);
        $group->refresh();
        $this->assertCount(0, $group->members);
    }

    public function test_member_index_filters_by_group(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['members:view']);
        $group = MemberGroup::factory()->forOrganization($data->organization)->create();
        $memberInGroup = Member::factory()->forOrganization($data->organization)->create();
        $memberNotInGroup = Member::factory()->forOrganization($data->organization)->create();
        $group->members()->attach($memberInGroup->getKey());
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(
            route('api.v1.members.index', $data->organization->getKey()).'?group_id='.$group->getKey()
        );

        // Assert
        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($memberInGroup->getKey(), $ids);
        $this->assertNotContains($memberNotInGroup->getKey(), $ids);
    }

    public function test_member_resource_includes_groups_field(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['members:view']);
        $group = MemberGroup::factory()->forOrganization($data->organization)->create([
            'name' => 'Engineering',
        ]);
        $group->members()->attach($data->member->getKey());
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.members.index', $data->organization->getKey()));

        // Assert
        $response->assertStatus(200);
        $found = collect($response->json('data'))->firstWhere('id', $data->member->getKey());
        $this->assertNotNull($found);
        $this->assertArrayHasKey('groups', $found);
        $this->assertSame([
            ['id' => $group->getKey(), 'name' => 'Engineering'],
        ], $found['groups']);
    }
}
