<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Insight extends Model
{
    use HasUuids;

    protected $fillable = [
        'title', 'description', 'type', 'impact', 'related_metric', 'domain_id', 'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
