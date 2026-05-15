<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Events\AfterCreateOrganization;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CreateOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizations_can_be_created(): void
    {
        // Arrange
        Config::set('app.enable_organization_creation', true);
        $user = User::factory()->withPersonalOrganization()->create();
        $this->actingAs($user);
        Event::fake([
            AfterCreateOrganization::class,
        ]);

        // Act
        $response = $this->post('/teams', [
            'name' => 'Test Organization',
        ]);

        // Assert
        $response->assertStatus(302);
        /** @var Organization|null $newOrganization */
        $ownedTeams = $user->fresh()->ownedTeams;
        $this->assertCount(2, $ownedTeams);
        $this->assertTrue($ownedTeams->contains('name', 'Test Organization'));
        $newOrganization = $ownedTeams->firstWhere('name', 'Test Organization');
        /** @var Member $member */
        $member = Member::query()->whereBelongsTo($user, 'user')->whereBelongsTo($newOrganization, 'organization')->firstOrFail();
        $this->assertSame(Role::Owner->value, $member->role);
        Event::assertDispatched(AfterCreateOrganization::class, function (AfterCreateOrganization $event) use ($newOrganization): bool {
            return $event->organization->is($newOrganization);
        });
    }

    public function test_organizations_can_not_be_created_when_disabled(): void
    {
        // Arrange
        Config::set('app.enable_organization_creation', false);
        $user = User::factory()->withPersonalOrganization()->create();
        $this->actingAs($user);

        // Act
        $response = $this->post('/teams', [
            'name' => 'Test Organization',
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertCount(1, $user->fresh()->ownedTeams);
    }

    public function test_super_admin_can_create_organization_when_disabled_in_config(): void
    {
        // Arrange
        Config::set('app.enable_organization_creation', false);
        Config::set('auth.super_admins', ['admin@example.com']);
        $user = User::factory()->withPersonalOrganization()->create([
            'email' => 'admin@example.com',
        ]);
        $this->actingAs($user);
        Event::fake([
            AfterCreateOrganization::class,
        ]);

        // Act
        $response = $this->post('/teams', [
            'name' => 'Super Admin Organization',
        ]);

        // Assert
        $response->assertStatus(302);
        $this->assertTrue($user->fresh()->ownedTeams->contains('name', 'Super Admin Organization'));
    }
}
