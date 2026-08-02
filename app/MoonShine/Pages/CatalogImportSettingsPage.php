<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use Domain\Catalog\Models\Category;
use Infrastructure\Settings\CatalogImportSettings;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use Services\CatalogImport\CategoryRegistry;

/**
 * Глобальные маркеры сегментов "Категория" для CategorySlugResolver — раньше
 * `config('catalog_import.category_resolution')`. Единственный писатель
 * CatalogImportSettings; читает их CategoryRegistry.
 */
#[Icon('cog-6-tooth')]
#[Group('moonshine.group.catalog', 'squares-2x2', translatable: true)]
#[Order(8)]
class CatalogImportSettingsPage extends Page
{
    public function getTitle(): string
    {
        return __('moonshine.catalog_import_settings.title');
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            '#' => $this->getTitle(),
        ];
    }

    /**
     * @return iterable<ComponentContract>
     */
    protected function components(): iterable
    {
        yield $this->form();
    }

    private function form(): FormBuilder
    {
        $settings = app(CatalogImportSettings::class);

        return FormBuilder::make()
            ->asyncMethod('store')
            ->fill(get_object_vars($settings))
            ->fields([
                Text::make(__('moonshine.catalog_import_settings.fields.alcohol_marker'), 'alcohol_marker')
                    ->required()
                    ->hint('Верхний сегмент колонки «Категория», отмечающий алкогольную продукцию.'),

                Text::make(__('moonshine.catalog_import_settings.fields.accessory_marker'), 'accessory_marker')
                    ->required()
                    ->hint('Верхний сегмент колонки «Категория», отмечающий сопутствующие товары.'),

                Text::make(__('moonshine.catalog_import_settings.fields.advent_marker'), 'advent_marker')
                    ->required()
                    ->hint('Подстрока в колонке «Категория», отмечающая адвент-календари.'),

                Select::make(__('moonshine.catalog_import_settings.fields.fallback_slug'), 'fallback_slug')
                    ->options(static fn (): array => Category::query()->orderBy('name')->pluck('name', 'slug')->all())
                    ->required()
                    ->hint('Категория, в которую попадают товары, не подошедшие ни под одно правило резолва.'),
            ])
            ->submit(__('moonshine::ui.save'));
    }

    #[AsyncMethod]
    public function store(): JsonResponse
    {
        $data = request()->validate([
            'alcohol_marker' => ['required', 'string', 'max:255'],
            'accessory_marker' => ['required', 'string', 'max:255'],
            'advent_marker' => ['required', 'string', 'max:255'],
            'fallback_slug' => ['required', 'string', 'exists:categories,slug'],
        ]);

        $settings = app(CatalogImportSettings::class);
        $settings->alcohol_marker = $data['alcohol_marker'];
        $settings->accessory_marker = $data['accessory_marker'];
        $settings->advent_marker = $data['advent_marker'];
        $settings->fallback_slug = $data['fallback_slug'];

        $settings->save();

        CategoryRegistry::flush();

        return JsonResponse::make()->toast(__('moonshine.catalog_import_settings.saved'));
    }
}
