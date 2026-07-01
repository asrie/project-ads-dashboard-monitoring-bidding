<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebVitalDaily extends Model
{
    use HasUuids;

    protected $table = 'web_vitals_daily';

    protected $fillable = [
        'date', 'domain_id', 'page_id', 'device',
        'lcp', 'inp', 'cls', 'fcp', 'ttfb', 'tbt', 'samples',
    ];

    protected $casts = [
        'date' => 'date',
        'lcp' => 'float',
        'inp' => 'float',
        'cls' => 'float',
        'fcp' => 'float',
        'ttfb' => 'float',
        'tbt' => 'float',
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
