<?php

namespace Services\CatalogImport;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Генерирует slug, гарантированно уникальный в рамках модели, добавляя
 * числовой суффикс при коллизии. Нужен там, где модель не использует
 * Spatie\Sluggable\HasSlug (Manufacturer, BeerStyle) — их slug/normalized_name
 * NOT NULL unique, но не генерируются автоматически моделью, а разные
 * normalized_name (напр. "Imperial Double-IPA" vs "Imperial Double IPA")
 * транслитерируются Str::slug() в один и тот же slug.
 */
final class UniqueSlugResolver
{
    /**
     * @param  class-string<Model>  $model
     */
    public function resolve(string $model, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while ($model::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
