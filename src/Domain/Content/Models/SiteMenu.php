<?php

namespace Domain\Content\Models;

use Database\Factories\Content\SiteMenuFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteMenu extends Model
{
    use HasFactory;

    // См. Domain\Content\Models\SiteSection::newFactory() — тот же пробел
    // конвенции для Domain\.
    protected static function newFactory(): Factory
    {
        return SiteMenuFactory::new();
    }

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'key',
        'title',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SiteMenuItem::class)->ordered();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_active'), true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy($query->qualifyColumn('title'))
            ->orderBy($query->qualifyColumn('id'));
    }
}
