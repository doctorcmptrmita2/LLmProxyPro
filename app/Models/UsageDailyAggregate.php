<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageDailyAggregate extends Model
{
    protected $fillable = [
        'project_id',
        'date',
        'total_tokens',
        'total_cost',
        'request_count',
    ];

    protected $casts = [
        'date' => 'date',
        'total_tokens' => 'integer',
        'total_cost' => 'decimal:6',
        'request_count' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

