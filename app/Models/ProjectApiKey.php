<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectApiKey extends Model
{
    protected $fillable = ['project_id', 'name', 'key_hash'];

    protected $hidden = ['key_hash'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function llmRequests(): HasMany
    {
        return $this->hasMany(LlmRequest::class, 'api_key_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}

