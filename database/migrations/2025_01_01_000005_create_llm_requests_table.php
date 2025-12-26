<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('api_key_id')->nullable()->constrained('project_api_keys')->onDelete('set null');
            $table->string('request_id')->unique();
            $table->enum('route_tier', ['fast', 'deep']);
            $table->string('model_requested')->nullable();
            $table->string('model_used');
            $table->string('provider');
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('cost', 10, 6)->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->boolean('cache_hit')->default(false);
            $table->unsignedSmallInteger('status_code')->default(200);
            $table->string('error_type')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_requests');
    }
};

