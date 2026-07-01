<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrebidAuction extends Model
{
    use HasUuids;

    protected $fillable = [
        'auction_id', 'domain_id', 'page_id', 'device', 'started_at',
        'duration_ms', 'bidder_count', 'bids_received', 'timeouts', 'errors',
        'won_bidder', 'cpm', 'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'cpm' => 'float',
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
