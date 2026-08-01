<?php

namespace Domain\Catalog\Models;

use Database\Factories\Catalog\PropertyFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'data_type',
        'unit',
        'storage_table',
        'storage_column',
    ];

    protected static function newFactory(): Factory
    {
        return PropertyFactory::new();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_properties')
            ->withPivot(['is_required', 'is_filterable', 'is_visible', 'sort_order']);
    }
}
