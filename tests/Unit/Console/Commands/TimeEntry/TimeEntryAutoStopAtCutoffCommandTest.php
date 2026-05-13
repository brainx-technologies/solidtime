<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\TimeEntry;

use App\Console\Commands\TimeEntry\TimeEntryAutoStopAtCutoffCommand;
use App\Enums\Role;
use App\Models\MemberTimeEntryEditOverride;
use App\Models\OrganizationTimeEntryEditPolicy;
use App\Models\TimeEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCaseWithDatabase;

#[CoversClass(TimeEntryAutoStopAtCutoffCommand::class)]
class TimeEntryAutoStopAtCutoffCommandTest extends TestCaseWithDatabase
{
    public function test_auto_stops_running_entry_when_lock_moment_passed(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);
        OrganizationTimeEntryEditPolicy::query()->create([
            'organization_id' => $data->organization->getKey(),
            'enabled' => true,
            'lock_after_days' => 1,
            'cutoff_time' => '09:00:00',
            'timezone' => 'Europe/Berlin',
        ]);
        $this->travelTo(Carbon::parse('2026-05-08 09:30:00', 'Europe/Berlin')->utc());
        $entry = TimeEntry::factory()->forOrganization($data->organization)->forMember($data->member)->create([
            'start' => Carbon::parse('2026-05-07 08:00:00', 'Europe/Berlin')->utc(),
            'end' => null,
        ]);

        // Act
        $exitCode = $this->withoutMockingConsoleOutput()->artisan('time-entry:auto-stop-at-cutoff');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $entry->refresh();
        $this->assertNotNull($entry->end);
        $this->assertSame(
            Carbon::parse('2026-05-08 09:00:00', 'Europe/Berlin')->utc()->subSecond()->timestamp,
            $entry->end->timestamp
        );
    }

    public function test_does_not_stop_running_entry_when_lock_moment_not_yet_reached(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);
        OrganizationTimeEntryEditPolicy::query()->create([
            'organization_id' => $data->organization->getKey(),
            'enabled' => true,
            'lock_after_days' => 1,
            'cutoff_time' => '09:00:00',
            'timezone' => 'Europe/Berlin',
        ]);
        $this->travelTo(Carbon::parse('2026-05-08 08:30:00', 'Europe/Berlin')->utc());
        $entry = TimeEntry::factory()->forOrganization($data->organization)->forMember($data->member)->create([
            'start' => Carbon::parse('2026-05-07 08:00:00', 'Europe/Berlin')->utc(),
            'end' => null,
        ]);

        // Act
        $exitCode = $this->withoutMockingConsoleOutput()->artisan('time-entry:auto-stop-at-cutoff');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $entry->refresh();
        $this->assertNull($entry->end);
    }

    public function test_does_not_stop_running_entry_when_policy_is_disabled(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);
        OrganizationTimeEntryEditPolicy::query()->create([
            'organization_id' => $data->organization->getKey(),
            'enabled' => false,
            'lock_after_days' => 1,
            'cutoff_time' => '09:00:00',
            'timezone' => 'Europe/Berlin',
        ]);
        $this->travelTo(Carbon::parse('2026-05-08 09:30:00', 'Europe/Berlin')->utc());
        $entry = TimeEntry::factory()->forOrganization($data->organization)->forMember($data->member)->create([
            'start' => Carbon::parse('2026-05-07 08:00:00', 'Europe/Berlin')->utc(),
            'end' => null,
        ]);

        // Act
        $exitCode = $this->withoutMockingConsoleOutput()->artisan('time-entry:auto-stop-at-cutoff');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $entry->refresh();
        $this->assertNull($entry->end);
    }

    public function test_does_not_stop_running_entry_when_member_has_active_override(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);
        OrganizationTimeEntryEditPolicy::query()->create([
            'organization_id' => $data->organization->getKey(),
            'enabled' => true,
            'lock_after_days' => 1,
            'cutoff_time' => '09:00:00',
            'timezone' => 'Europe/Berlin',
        ]);
        $this->travelTo(Carbon::parse('2026-05-08 09:30:00', 'Europe/Berlin')->utc());
        MemberTimeEntryEditOverride::query()->create([
            'organization_id' => $data->organization->getKey(),
            'member_id' => $data->member->getKey(),
            'applies_on' => '2026-05-07',
            'editable_until' => Carbon::parse('2026-05-08 11:00:00', 'Europe/Berlin')->utc(),
        ]);
        $entry = TimeEntry::factory()->forOrganization($data->organization)->forMember($data->member)->create([
            'start' => Carbon::parse('2026-05-07 08:00:00', 'Europe/Berlin')->utc(),
            'end' => null,
        ]);

        // Act
        $exitCode = $this->withoutMockingConsoleOutput()->artisan('time-entry:auto-stop-at-cutoff');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $entry->refresh();
        $this->assertNull($entry->end);
    }

    public function test_does_not_stop_running_entries_for_members_with_time_entry_all_permissions(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Admin);
        OrganizationTimeEntryEditPolicy::query()->create([
            'organization_id' => $data->organization->getKey(),
            'enabled' => true,
            'lock_after_days' => 1,
            'cutoff_time' => '09:00:00',
            'timezone' => 'Europe/Berlin',
        ]);
        $this->travelTo(Carbon::parse('2026-05-08 09:30:00', 'Europe/Berlin')->utc());
        $entry = TimeEntry::factory()->forOrganization($data->organization)->forMember($data->member)->create([
            'start' => Carbon::parse('2026-05-07 08:00:00', 'Europe/Berlin')->utc(),
            'end' => null,
        ]);

        // Act
        $exitCode = $this->withoutMockingConsoleOutput()->artisan('time-entry:auto-stop-at-cutoff');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $entry->refresh();
        $this->assertNull($entry->end);
    }

    public function test_does_not_fetch_running_entry_started_before_lock_after_window(): void
    {
        // Arrange: after cutoff; start is two local days ago — not in [yesterday 0:00, today 0:00) for lock_after_days = 1
        $data = $this->createUserWithRole(Role::Employee);
        OrganizationTimeEntryEditPolicy::query()->create([
            'organization_id' => $data->organization->getKey(),
            'enabled' => true,
            'lock_after_days' => 1,
            'cutoff_time' => '09:00:00',
            'timezone' => 'Europe/Berlin',
        ]);
        $this->travelTo(Carbon::parse('2026-05-08 09:30:00', 'Europe/Berlin')->utc());
        $entry = TimeEntry::factory()->forOrganization($data->organization)->forMember($data->member)->create([
            'start' => Carbon::parse('2026-05-06 08:00:00', 'Europe/Berlin')->utc(),
            'end' => null,
        ]);

        // Act
        $exitCode = $this->withoutMockingConsoleOutput()->artisan('time-entry:auto-stop-at-cutoff');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $entry->refresh();
        $this->assertNull($entry->end);
    }

    public function test_dry_run_does_not_persist_end(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);
        OrganizationTimeEntryEditPolicy::query()->create([
            'organization_id' => $data->organization->getKey(),
            'enabled' => true,
            'lock_after_days' => 1,
            'cutoff_time' => '09:00:00',
            'timezone' => 'Europe/Berlin',
        ]);
        $this->travelTo(Carbon::parse('2026-05-08 09:30:00', 'Europe/Berlin')->utc());
        $entry = TimeEntry::factory()->forOrganization($data->organization)->forMember($data->member)->create([
            'start' => Carbon::parse('2026-05-07 08:00:00', 'Europe/Berlin')->utc(),
            'end' => null,
        ]);

        // Act
        $exitCode = $this->withoutMockingConsoleOutput()->artisan('time-entry:auto-stop-at-cutoff --dry-run');

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode);
        $entry->refresh();
        $this->assertNull($entry->end);
    }
}
