<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmRequest extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'project_id',
        'user_id',
        'api_key_id',
        'request_id',
        'route_tier',
        'model_requested',
        'model_used',
        'provider',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost',
        'latency_ms',
        'cache_hit',
        'status_code',
        'error_type',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'cost' => 'decimal:6',
        'latency_ms' => 'integer',
        'cache_hit' => 'boolean',
        'status_code' => 'integer',
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ProjectApiKey::class, 'api_key_id');
    }
}

