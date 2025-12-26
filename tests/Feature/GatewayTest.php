<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GatewayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $org;
    private Project $project;
    private ProjectApiKey $apiKey;
    private string $plainKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->org = Organization::create(['name' => 'Test Org']);
        $this->org->users()->attach($this->user->id, ['role' => 'owner']);

        $this->project = Project::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Project',
            'plan_code' => 'free',
        ]);

        $this->plainKey = 'sk_test_' . str_random(32);
        $this->apiKey = ProjectApiKey::create([
            'project_id' => $this->project->id,
            'name' => 'Test Key',
            'key_hash' => Hash::make($this->plainKey),
        ]);
    }

    public function test_gateway_success(): void
    {
        $response = $this->postJson('/api/v1/chat/completions', [
            'model' => 'anthropic/claude-haiku-4-5',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'temperature' => 0.7,
            'max_tokens' => 100,
        ], [
            'Authorization' => "Bearer {$this->plainKey}",
            'X-Request-Id' => 'test-request-123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'choices' => [
                '*' => ['message' => ['content']],
            ],
            'usage' => ['prompt_tokens', 'completion_tokens', 'total_tokens'],
        ]);
    }

    public function test_gateway_missing_api_key(): void
    {
        $response = $this->postJson('/api/v1/chat/completions', [
            'model' => 'anthropic/claude-haiku-4-5',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error.type', 'authentication_error');
    }

    public function test_gateway_invalid_api_key(): void
    {
        $response = $this->postJson('/api/v1/chat/completions', [
            'model' => 'anthropic/claude-haiku-4-5',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ], [
            'Authorization' => 'Bearer invalid_key_123',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error.type', 'authentication_error');
    }

    public function test_gateway_revoked_api_key(): void
    {
        $this->apiKey->update(['revoked_at' => now()]);

        $response = $this->postJson('/api/v1/chat/completions', [
            'model' => 'anthropic/claude-haiku-4-5',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ], [
            'Authorization' => "Bearer {$this->plainKey}",
        ]);

        $response->assertStatus(401);
    }

    public function test_gateway_invalid_payload(): void
    {
        $response = $this->postJson('/api/v1/chat/completions', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ], [
            'Authorization' => "Bearer {$this->plainKey}",
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.model', ['The model field is required.']);
    }

    public function test_gateway_limit_exceeded(): void
    {
        $this->project->update(['monthly_token_limit' => 100]);

        $response = $this->postJson('/api/v1/chat/completions', [
            'model' => 'anthropic/claude-haiku-4-5',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
            ],
            'max_tokens' => 200,
        ], [
            'Authorization' => "Bearer {$this->plainKey}",
        ]);

        $response->assertStatus(429);
        $response->assertJsonPath('error.type', 'quota_exceeded');
    }
}

