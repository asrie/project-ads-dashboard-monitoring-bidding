<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagePreview extends Model
{
    use HasUuids;

    protected $fillable = [
        'domain_id', 'page_id', 'device', 'url', 'status', 'image_path',
        'page_width', 'page_height', 'viewport_css_width', 'slot_count',
        'slots', 'header', 'error', 'requested_by', 'captured_at',
    ];

    protected $casts = [
        'slots' => 'array',
        'header' => 'array',
        'page_width' => 'integer',
        'page_height' => 'integer',
        'viewport_css_width' => 'integer',
        'slot_count' => 'integer',
        'captured_at' => 'datetime',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
