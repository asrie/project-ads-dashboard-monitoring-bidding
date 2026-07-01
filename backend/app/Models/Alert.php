<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertCategory;
use App\Enums\AlertStatus;
use App\Enums\Severity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasUuids;

    protected $fillable = [
        'severity', 'category', 'metric', 'current_value', 'threshold_value',
        'entity_type', 'entity_id', 'entity_label', 'domain_id', 'message',
        'suggested_action', 'status', 'acknowledged_by', 'acknowledged_at', 'triggered_at',
    ];

    protected $casts = [
        'severity' => Severity::class,
        'category' => AlertCategory::class,
        'status' => AlertStatus::class,
        'current_value' => 'float',
        'threshold_value' => 'float',
        'acknowledged_at' => 'datetime',
        'triggered_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
