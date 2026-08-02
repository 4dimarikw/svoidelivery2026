<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\CategoryMatchRuleFactory;
use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Enums\CategoryMatchWhen;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Services\CatalogImport\CategoryRegistry;

/**
 * One slug-resolution rule for a Category, formerly one entry in that
 * category's `match[]` array in config/catalog_import.php. Read exclusively
 * through Services\CatalogImport\CategoryRegistry — see that class and
 * Services\CatalogImport\CategorySlugResolver for how `type`/`match_when`/
 * `value`/`priority` are interpreted.
 */
class CategoryMatchRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'type',
        'match_when',
        'value',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CategoryMatchType::class,
            'match_when' => CategoryMatchWhen::class,
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return CategoryMatchRuleFactory::new();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        static::saved(fn () => CategoryRegistry::flush());
        static::deleted(fn () => CategoryRegistry::flush());
    }
}
