<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'url', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(AdSlot::class);
    }
}
