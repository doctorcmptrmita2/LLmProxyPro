<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyManagementTest extends TestCase
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

    public function test_create_api_key(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/projects/{$this->project->id}/keys", [
                'name' => 'My API Key',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'name',
            'key',
            'created_at',
        ]);

        $this->assertStringStartsWith('sk_', $response->json('key'));
    }

    public function test_list_api_keys(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/projects/{$this->project->id}/keys", [
                'name' => 'Key 1',
            ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/projects/{$this->project->id}/keys");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_revoke_api_key(): void
    {
        $createResponse = $this->actingAs($this->user)
            ->postJson("/api/v1/projects/{$this->project->id}/keys", [
                'name' => 'Key to Revoke',
            ]);

        $keyId = $createResponse->json('id');

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/projects/{$this->project->id}/keys/{$keyId}");

        $response->assertStatus(204);

        $listResponse = $this->actingAs($this->user)
            ->getJson("/api/v1/projects/{$this->project->id}/keys");

        $this->assertNotNull($listResponse->json('0.revoked_at'));
    }
}

