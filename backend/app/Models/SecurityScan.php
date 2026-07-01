<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityScan extends Model
{
    use HasUuids;

    protected $fillable = [
        'domain_id', 'target_url', 'target_host', 'status', 'grade', 'score',
        'results', 'error', 'requested_by', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'results' => 'array',
        'score' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
