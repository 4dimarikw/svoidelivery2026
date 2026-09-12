<?php

namespace App\Console\Commands;

use Domain\Catalog\Models\Category;
use Illuminate\Console\Command;
use Infrastructure\Settings\CatalogImportSettings;
use InvalidArgumentException;
use Services\CatalogImport\CategoryMatchingRules;

/** Проверяет конфиг правил и ссылки на категории до запуска импорта. */
class ValidateCategoryRegistryCommand extends Command
{
    protected $signature = 'catalog:validate-registry';

    protected $description = 'Проверить правила категоризации и резервную категорию';

    public function handle(CatalogImportSettings $settings): int
    {
        try {
            $rules = CategoryMatchingRules::ordered(config('category_matching.rules'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $categories = Category::query()->get(['slug', 'is_active'])->keyBy('slug');
        $missing = array_diff(array_unique(array_column($rules, 'slug')), $categories->keys()->all());
        if ($missing !== []) {
            $this->error('Категории правил не найдены: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $fallback = $categories->get($settings->fallback_slug);
        if ($fallback === null) {
            $this->error("fallback_slug '{$settings->fallback_slug}' не найден среди категорий.");

            return self::FAILURE;
        }

        if (! $fallback->is_active) {
            $this->warn("fallback_slug '{$settings->fallback_slug}' — категория неактивна: товары скрыты с витрины.");
        }

        $this->info('Реестр категорий валиден.');

        return self::SUCCESS;
    }
}
