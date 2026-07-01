<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotPerformanceDaily extends Model
{
    use HasUuids;

    protected $table = 'slot_performance_daily';

    protected $fillable = [
        'date', 'domain_id', 'slot_id', 'device',
        'ad_requests', 'impressions', 'revenue', 'ecpm', 'fill_rate', 'viewability',
    ];

    protected $casts = [
        'date' => 'date',
        'revenue' => 'float',
        'ecpm' => 'float',
        'fill_rate' => 'float',
        'viewability' => 'float',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(AdSlot::class, 'slot_id');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
