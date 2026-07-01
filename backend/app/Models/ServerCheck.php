<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerCheck extends Model
{
    use HasUuids;

    protected $fillable = [
        'domain_id', 'checked_at', 'status', 'response_time_ms',
        'http_status', 'region', 'error_message',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
