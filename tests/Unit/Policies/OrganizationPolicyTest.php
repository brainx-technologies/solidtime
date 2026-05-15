<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(\App\Policies\OrganizationPolicy::class)]
class OrganizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_not_create_organization_when_disabled_in_config(): void
    {
        // Arrange
        Config::set('app.enable_organization_creation', false);
        $user = User::factory()->withPersonalOrganization()->create();

        // Act
        $canCreate = Gate::forUser($user)->check('create', Organization::class);

        // Assert
        $this->assertFalse($canCreate);
    }

    public function test_user_can_create_organization_when_enabled_in_config(): void
    {
        // Arrange
        Config::set('app.enable_organization_creation', true);
        $user = User::factory()->withPersonalOrganization()->create();

        // Act
        $canCreate = Gate::forUser($user)->check('create', Organization::class);

        // Assert
        $this->assertTrue($canCreate);
    }

    public function test_super_admin_can_create_organization_when_disabled_in_config(): void
    {
        // Arrange
        Config::set('app.enable_organization_creation', false);
        Config::set('auth.super_admins', ['admin@example.com']);
        $user = User::factory()->withPersonalOrganization()->create([
            'email' => 'admin@example.com',
        ]);

        // Act
        $canCreate = Gate::forUser($user)->check('create', Organization::class);

        // Assert
        $this->assertTrue($canCreate);
    }
}
