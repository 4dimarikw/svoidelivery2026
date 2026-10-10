<?php

declare(strict_types=1);

namespace Domain\Catalog\Actions;

use Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Spatie\Sluggable\Actions\GenerateSlugAction;
use Spatie\Sluggable\SlugOptions;

final class GenerateProductSlugAction extends GenerateSlugAction
{
    protected function generateNonUniqueSlug(Model $model, SlugOptions $options): string
    {
        if ($this->hasCustomSlugBeenUsed($model, $options) && filled($model->{$options->slugField})) {
            Validator::make(['slug' => $model->{$options->slugField}], [
                'slug' => ['string', 'max:'.Product::SLUG_MAX_LENGTH, 'regex:/\A[a-z0-9-]+\z/'],
            ])->validate();
        }

        return parent::generateNonUniqueSlug($model, $options);
    }

    public function slugifySource(string $source, SlugOptions $options): string
    {
        // Транслитерация может увеличить длину: ограничиваем уже готовый ASCII-slug.
        $slug = preg_replace('/[^a-z0-9-]/', '', parent::slugifySource($source, $options));

        return rtrim(substr($slug, 0, Product::SLUG_MAX_LENGTH), '-');
    }

    public function makeUnique(string $slug, Model $model, SlugOptions $options): string
    {
        $candidate = parent::makeUnique($slug, $model, $options);
        if (strlen($candidate) <= Product::SLUG_MAX_LENGTH) {
            return $candidate;
        }

        // Суффикс уникальности тоже входит в лимит; после обрезки повторно проверяем коллизии.
        $iteration = 0;
        do {
            $suffix = $options->slugSeparator.$this->generateSuffix($slug, $iteration++, $options);
            $candidate = rtrim(substr($slug, 0, Product::SLUG_MAX_LENGTH - strlen($suffix)), '-').$suffix;
        } while ($this->otherRecordExistsWithSlug($candidate, $model, $options));

        return $candidate;
    }
}
