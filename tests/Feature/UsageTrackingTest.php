<?php

namespace Tests\Feature;

use App\Models\LlmRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Models\UsageDailyAggregate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->org = Organization::create(['name' => 'Test Org']);
        $this->org->users()->attach($this->user->id, ['role' => 'owner']);

        $this->project = Project::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Project',
        ]);
    }

    public function test_get_daily_usage(): void
    {
        $today = now()->toDateString();

        UsageDailyAggregate::create([
            'project_id' => $this->project->id,
            'date' => $today,
            'total_tokens' => 1000,
            'total_cost' => 0.01,
            'request_count' => 5,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/usage/daily', [
                'from' => $today,
                'to' => $today,
                'project_id' => $this->project->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.total_tokens', 1000);
        $response->assertJsonPath('data.0.total_cost', 0.01);
    }

    public function test_get_monthly_summary(): void
    {
        $month = now()->format('Y-m');
        $today = now()->toDateString();

        UsageDailyAggregate::create([
            'project_id' => $this->project->id,
            'date' => $today,
            'total_tokens' => 5000,
            'total_cost' => 0.05,
            'request_count' => 10,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/usage/summary', [
                'month' => $month,
                'project_id' => $this->project->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('total_tokens', 5000);
        $response->assertJsonPath('total_cost', 0.05);
        $response->assertJsonPath('request_count', 10);
    }
}

