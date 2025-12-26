<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'plan_code',
        'monthly_token_limit',
        'monthly_cost_limit',
    ];

    protected $casts = [
        'monthly_token_limit' => 'integer',
        'monthly_cost_limit' => 'decimal:6',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ProjectApiKey::class);
    }

    public function llmRequests(): HasMany
    {
        return $this->hasMany(LlmRequest::class);
    }

    public function usageDailyAggregates(): HasMany
    {
        return $this->hasMany(UsageDailyAggregate::class);
    }
}

