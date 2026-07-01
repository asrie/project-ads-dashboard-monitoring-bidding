<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkAdRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'domain_id', 'page_id', 'device', 'observed_at', 'resource_url',
        'vendor', 'type', 'size_bytes', 'duration_ms', 'is_third_party',
        'is_blocking', 'status_code',
    ];

    protected $casts = [
        'observed_at' => 'datetime',
        'is_third_party' => 'boolean',
        'is_blocking' => 'boolean',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
