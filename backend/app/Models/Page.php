<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    use HasUuids;

    protected $fillable = ['domain_id', 'path', 'title'];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
