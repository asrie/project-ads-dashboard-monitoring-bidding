<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidderPerformanceDaily extends Model
{
    use HasUuids;

    protected $table = 'bidder_performance_daily';

    protected $fillable = [
        'date', 'domain_id', 'bidder_id',
        'bid_requests', 'bid_responses', 'bids_won', 'timeouts', 'errors',
        'avg_latency_ms', 'revenue', 'avg_cpm',
    ];

    protected $casts = [
        'date' => 'date',
        'avg_latency_ms' => 'float',
        'revenue' => 'float',
        'avg_cpm' => 'float',
    ];

    public function bidder(): BelongsTo
    {
        return $this->belongsTo(Bidder::class);
    }
}
