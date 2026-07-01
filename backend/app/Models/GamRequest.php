<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'domain_id', 'page_id', 'device', 'requested_at', 'ad_unit',
        'status', 'latency_ms', 'http_status', 'line_item_id', 'creative_id',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
