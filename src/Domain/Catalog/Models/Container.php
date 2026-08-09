<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\ContainerFactory;
use Domain\Catalog\Filters\FilterOptionsRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Container extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ContainerFactory::new();
    }

    /**
     * Значения этого справочника кешируются целиком в FilterOptionsRegistry —
     * см. её докблок.
     */
    protected static function booted(): void
    {
        static::saved(fn () => FilterOptionsRegistry::flush());
        static::deleted(fn () => FilterOptionsRegistry::flush());
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
