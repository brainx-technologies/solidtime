<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Api\V1\SummaryReportController;
use App\Models\Member;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(SummaryReportController::class)]
class SummaryReportEndpointTest extends ApiEndpointTestAbstract
{
    public function test_summary_endpoint_fails_without_view_all_time_entries_permission(): void
    {
        $data = $this->createUserWithPermission([
            'time-entries:view:own',
        ]);
        Passport::actingAs($data->user);

        $response = $this->postJson(
            route('api.v1.summary-reports.summary', $data->organization->getKey()),
            [
                'dateRangeStart' => '2024-01-01',
                'dateRangeEnd' => '2024-01-02',
                'summaryFilter' => ['groups' => ['PROJECT']],
            ]
        );

        $response->assertStatus(403);
    }

    public function test_summary_endpoint_returns_totals_with_no_grouping(): void
    {
        $data = $this->createUserWithPermission([
            'time-entries:view:all',
        ]);
        TimeEntry::factory()
            ->forOrganization($data->organization)
            ->forMember($data->member)
            ->createMany([
                ['start' => Carbon::create(2024, 1, 1, 10, 0, 0, 'UTC'), 'end' => Carbon::create(2024, 1, 1, 11, 0, 0, 'UTC')],
                ['start' => Carbon::create(2024, 1, 1, 12, 0, 0, 'UTC'), 'end' => Carbon::create(2024, 1, 1, 13, 30, 0, 'UTC')],
            ]);
        Passport::actingAs($data->user);

        $response = $this->postJson(
            route('api.v1.summary-reports.summary', $data->organization->getKey()),
            [
                'dateRangeStart' => '2024-01-01',
                'dateRangeEnd' => '2024-01-02',
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('totals.0._id', 'TOTAL');
        $response->assertJsonPath('timeEntriesCount', 2);
        $response->assertJsonPath('groupOne', []);
    }

    public function test_summary_endpoint_with_two_level_grouping_returns_nested_children(): void
    {
        $data = $this->createUserWithPermission([
            'time-entries:view:all',
        ]);
        $project = Project::factory()->forOrganization($data->organization)->create(['name' => 'Alpha']);
        TimeEntry::factory()
            ->forOrganization($data->organization)
            ->forMember($data->member)
            ->forProject($project)
            ->create([
                'start' => Carbon::create(2024, 1, 1, 10, 0, 0, 'UTC'),
                'end' => Carbon::create(2024, 1, 1, 11, 0, 0, 'UTC'),
            ]);
        Passport::actingAs($data->user);

        $response = $this->postJson(
            route('api.v1.summary-reports.summary', $data->organization->getKey()),
            [
                'dateRangeStart' => '2024-01-01',
                'dateRangeEnd' => '2024-01-02',
                'summaryFilter' => ['groups' => ['PROJECT', 'USER']],
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('groupOne.0._id', $project->getKey());
        $response->assertJsonPath('groupOne.0.name', 'Alpha');
        $response->assertJsonPath('groupOne.0.children.0._id', $data->member->getKey());
        $response->assertJsonPath('groupOne.0.duration', 3600);
    }

    public function test_summary_endpoint_normalizes_billable_grouping_keys(): void
    {
        $data = $this->createUserWithPermission([
            'time-entries:view:all',
        ]);
        TimeEntry::factory()
            ->forOrganization($data->organization)
            ->forMember($data->member)
            ->createMany([
                ['billable' => true, 'start' => Carbon::create(2024, 1, 1, 10, 0, 0, 'UTC'), 'end' => Carbon::create(2024, 1, 1, 11, 0, 0, 'UTC')],
                ['billable' => false, 'start' => Carbon::create(2024, 1, 1, 12, 0, 0, 'UTC'), 'end' => Carbon::create(2024, 1, 1, 13, 0, 0, 'UTC')],
            ]);
        Passport::actingAs($data->user);

        $response = $this->postJson(
            route('api.v1.summary-reports.summary', $data->organization->getKey()),
            [
                'dateRangeStart' => '2024-01-01',
                'dateRangeEnd' => '2024-01-02',
                'summaryFilter' => ['groups' => ['BILLABLE']],
            ]
        );

        $response->assertStatus(200);
        $ids = collect($response->json('groupOne'))->pluck('_id')->all();
        $this->assertEqualsCanonicalizing(['BILLABLE', 'NON_BILLABLE'], $ids);
        $names = collect($response->json('groupOne'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Billable', 'Non-billable'], $names);
    }

    public function test_summary_endpoint_accepts_same_day_range(): void
    {
        $data = $this->createUserWithPermission([
            'time-entries:view:all',
        ]);
        Passport::actingAs($data->user);

        $response = $this->postJson(
            route('api.v1.summary-reports.summary', $data->organization->getKey()),
            [
                'dateRangeStart' => '2024-01-01',
                'dateRangeEnd' => '2024-01-01',
            ]
        );

        $response->assertStatus(200);
    }

    public function test_summary_endpoint_rejects_more_than_three_groups(): void
    {
        $data = $this->createUserWithPermission([
            'time-entries:view:all',
        ]);
        Passport::actingAs($data->user);

        $response = $this->postJson(
            route('api.v1.summary-reports.summary', $data->organization->getKey()),
            [
                'dateRangeStart' => '2024-01-01',
                'dateRangeEnd' => '2024-01-02',
                'summaryFilter' => ['groups' => ['PROJECT', 'USER', 'TASK', 'CLIENT']],
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('summaryFilter.groups');
    }

    public function test_summary_endpoint_rejects_user_id_from_other_organization(): void
    {
        $data = $this->createUserWithPermission([
            'time-entries:view:all',
        ]);
        $foreign = $this->createUserWithPermission(['members:view']);
        $foreignMember = Member::factory()->forOrganization($foreign->organization)->create();
        Passport::actingAs($data->user);

        $response = $this->postJson(
            route('api.v1.summary-reports.summary', $data->organization->getKey()),
            [
                'dateRangeStart' => '2024-01-01',
                'dateRangeEnd' => '2024-01-02',
                'users' => ['ids' => [$foreignMember->getKey()]],
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('users.ids.0');
    }

    public function test_summary_endpoint_rejects_duplicate_groups(): void
    {
        $data = $this->createUserWithPermission([
            'time-entries:view:all',
        ]);
        Passport::actingAs($data->user);

        $response = $this->postJson(
            route('api.v1.summary-reports.summary', $data->organization->getKey()),
            [
                'dateRangeStart' => '2024-01-01',
                'dateRangeEnd' => '2024-01-02',
                'summaryFilter' => ['groups' => ['PROJECT', 'PROJECT']],
            ]
        );

        $response->assertStatus(422);
    }

    public function test_summary_endpoint_hides_amount_for_employee_when_org_disallows_billable_rate(): void
    {
        // An Employee role normally lacks time-entries:view:all; grant it explicitly via
        // a custom role so we can isolate the billable-rate suppression branch.
        $data = $this->createUserWithPermission([
            'time-entries:view:all',
        ]);
        // Force the member into the Employee role so the controller's role check is exercised
        // while the custom role still grants the permission.
        Member::query()->where('id', $data->member->getKey())->update([
            'role' => Role::Employee->value,
        ]);
        $data->organization->employees_can_see_billable_rates = false;
        $data->organization->save();

        TimeEntry::factory()
            ->forOrganization($data->organization)
            ->forMember($data->member)
            ->create([
                'start' => Carbon::create(2024, 1, 1, 10, 0, 0, 'UTC'),
                'end' => Carbon::create(2024, 1, 1, 11, 0, 0, 'UTC'),
                'billable_rate' => 6000,
                'billable' => true,
            ]);
        Passport::actingAs($data->user);

        $response = $this->postJson(
            route('api.v1.summary-reports.summary', $data->organization->getKey()),
            [
                'dateRangeStart' => '2024-01-01',
                'dateRangeEnd' => '2024-01-02',
                'summaryFilter' => ['groups' => ['PROJECT']],
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('totals.0.amount', null);
    }
}
