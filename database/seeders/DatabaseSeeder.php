<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectApiKey;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@codexflow.dev',
            'password' => Hash::make('password'),
        ]);

        $org = Organization::create([
            'name' => 'Test Organization',
        ]);

        $org->users()->attach($user->id, ['role' => 'owner']);

        $project = Project::create([
            'organization_id' => $org->id,
            'name' => 'Test Project',
            'plan_code' => 'free',
            'monthly_token_limit' => 1000000,
            'monthly_cost_limit' => 100.00,
        ]);

        $plainKey = 'sk_test_' . Str::random(32);
        ProjectApiKey::create([
            'project_id' => $project->id,
            'name' => 'Test API Key',
            'key_hash' => Hash::make($plainKey),
        ]);

        echo "\n✓ Test user created: test@codexflow.dev (password: password)\n";
        echo "✓ Test organization created\n";
        echo "✓ Test project created\n";
        echo "✓ Test API key created: {$plainKey}\n\n";
    }
}
