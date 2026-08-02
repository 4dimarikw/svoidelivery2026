<?php

namespace App\Console\Commands;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Enums\CategoryMatchWhen;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\CategoryMatchRule;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Infrastructure\Settings\CatalogImportSettings;

/**
 * Проверяет инварианты реестра категорий, которые раньше держались только
 * «правильным порядком объявления» в config/catalog_import.php, а теперь,
 * когда реестр редактируется из MoonShine, могут быть нарушены руками:
 *
 * 1. type=alcohol + match_when ∈ {advent, no_abv, default} — не больше
 *    одного активного правила на каждое значение (CategorySlugResolver::
 *    slugForAlcoholWhen() берёт первое и молча затеняет остальные).
 * 2. type=alcohol + match_when=style — value (keyword) обязателен.
 * 3. type=contains — value (needle) обязателен, match_when должен быть пуст.
 * 4. type=accessory_title — value обязателен и глобально уникален среди
 *    активных правил (дубль тихо перезаписывается последним объявлением).
 * 5. CatalogImportSettings::fallback_slug должен указывать на существующую
 *    активную категорию.
 */
class ValidateCategoryRegistryCommand extends Command
{
    protected $signature = 'catalog:validate-registry';

    protected $description = 'Проверить инварианты реестра категорий (правила резолва slug, fallback)';

    public function handle(CatalogImportSettings $settings): int
    {
        $errors = [];

        $rules = CategoryMatchRule::query()->active()->with('category:id,slug')->get();

        $this->checkAlcoholWhenUniqueness($rules, $errors);
        $this->checkRequiredValues($rules, $errors);
        $this->checkAccessoryTitleUniqueness($rules, $errors);
        $this->checkFallback($settings, $errors);

        if ($errors === []) {
            $this->info('Реестр категорий валиден.');

            return self::SUCCESS;
        }

        $this->error('Найдены нарушения инвариантов реестра категорий:');
        foreach ($errors as $error) {
            $this->line("  - {$error}");
        }

        return self::FAILURE;
    }

    /**
     * @param  Collection<int, CategoryMatchRule>  $rules
     * @param  list<string>  $errors
     */
    private function checkAlcoholWhenUniqueness($rules, array &$errors): void
    {
        $byWhen = $rules
            ->filter(fn (CategoryMatchRule $r) => $r->type === CategoryMatchType::Alcohol
                && in_array($r->match_when, [CategoryMatchWhen::Advent, CategoryMatchWhen::NoAbv, CategoryMatchWhen::DefaultRule], true))
            ->groupBy(fn (CategoryMatchRule $r) => $r->match_when->value);

        foreach ($byWhen as $when => $group) {
            if ($group->count() > 1) {
                $slugs = $group->map(fn (CategoryMatchRule $r) => $r->category->slug)->implode(', ');
                $errors[] = "Несколько активных правил alcohol/when={$when}: {$slugs} — только первое будет учтено.";
            }
        }
    }

    /**
     * @param  Collection<int, CategoryMatchRule>  $rules
     * @param  list<string>  $errors
     */
    private function checkRequiredValues($rules, array &$errors): void
    {
        foreach ($rules as $rule) {
            $slug = $rule->category->slug;

            if ($rule->type === CategoryMatchType::Alcohol && $rule->match_when === CategoryMatchWhen::Style && blank($rule->value)) {
                $errors[] = "Категория '{$slug}': правило alcohol/style без value (keyword).";
            }

            if ($rule->type === CategoryMatchType::Contains) {
                if (blank($rule->value)) {
                    $errors[] = "Категория '{$slug}': правило contains без value (needle).";
                }
                if ($rule->match_when !== null) {
                    $errors[] = "Категория '{$slug}': правило contains не должно иметь match_when.";
                }
            }

            if ($rule->type === CategoryMatchType::AccessoryTitle && blank($rule->value)) {
                $errors[] = "Категория '{$slug}': правило accessory_title без value (keyword).";
            }
        }
    }

    /**
     * @param  Collection<int, CategoryMatchRule>  $rules
     * @param  list<string>  $errors
     */
    private function checkAccessoryTitleUniqueness($rules, array &$errors): void
    {
        $duplicates = $rules
            ->filter(fn (CategoryMatchRule $r) => $r->type === CategoryMatchType::AccessoryTitle && filled($r->value))
            ->groupBy(fn (CategoryMatchRule $r) => mb_strtolower($r->value))
            ->filter(fn ($group) => $group->count() > 1);

        foreach ($duplicates as $keyword => $group) {
            $slugs = $group->map(fn (CategoryMatchRule $r) => $r->category->slug)->implode(', ');
            $errors[] = "Ключевое слово accessory_title '{$keyword}' встречается в нескольких категориях: {$slugs} — выигрывает только одна.";
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function checkFallback(CatalogImportSettings $settings, array &$errors): void
    {
        $category = Category::query()->where('slug', $settings->fallback_slug)->first();

        if ($category === null) {
            $errors[] = "fallback_slug '{$settings->fallback_slug}' не найден среди категорий.";
        } elseif (! $category->is_active) {
            $errors[] = "fallback_slug '{$settings->fallback_slug}' указывает на неактивную категорию.";
        }
    }
}
