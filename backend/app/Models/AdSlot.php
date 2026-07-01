<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdSlot extends Model
{
    use HasUuids;

    protected $table = 'ad_slots';

    protected $fillable = ['domain_id', 'name', 'ad_unit_path', 'sizes', 'device', 'is_active'];

    protected $casts = [
        'sizes' => 'array',
        'is_active' => 'boolean',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function performance(): HasMany
    {
        return $this->hasMany(SlotPerformanceDaily::class, 'slot_id');
    }
}
