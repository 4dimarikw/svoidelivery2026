<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\Category;
use Illuminate\Validation\Rule;
use Infrastructure\Settings\CatalogImportSettings;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Services\CatalogImport\CategoryRegistry;

/**
 * Настройки импорта каталога 1С: маркеры сегментов "Категория" для
 * CategorySlugResolver (читает их CategoryRegistry), предохранитель
 * обнуления остатков (см. ZeroOutStaleProductsAction), а также настройки,
 * перенесённые из GeneralSettings (product_status, product_flags, new_days,
 * cache, last_catalog_update) — они логически про каталог/импорт, а не про
 * «общие» настройки сайта. Единственный писатель CatalogImportSettings.
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
            ->fill([
                ...get_object_vars($settings),
                'flag_wu' => $settings->product_flags['wu'] ?? false,
                'flag_fil' => $settings->product_flags['fil'] ?? false,
                'flag_mss' => $settings->product_flags['mss'] ?? false,
                'flag_promo' => $settings->product_flags['promo'] ?? false,
            ])
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

                Switcher::make(__('moonshine.catalog_import_settings.fields.zero_out_missing'), 'zero_out_missing')
                    ->hint('После успешного импорта (не dry-run, без фильтра категорий) обнулять остаток у товаров, отсутствующих в выгрузке.'),

                Number::make(__('moonshine.catalog_import_settings.fields.zero_out_max_percent'), 'zero_out_max_percent')
                    ->min(1)
                    ->max(100)
                    ->required()
                    ->hint('Предохранитель: если доля пропавших товаров превышает этот процент от числа товаров в наличии — обнуление отменяется целиком (признак битого/обрезанного CSV).'),

                Select::make(__('moonshine.catalog_import_settings.fields.product_status'), 'product_status')
                    ->options(collect(ProductStatus::cases())->mapWithKeys(
                        static fn (ProductStatus $status): array => [$status->value => $status->toString()]
                    )->all())
                    ->required()
                    ->hint('Статус, с которым создаются новые товары при первом импорте.'),

                Number::make(__('moonshine.catalog_import_settings.fields.new_days'), 'new_days')
                    ->min(1)
                    ->required()
                    ->hint('Число дней, в течение которых товар считается «новинкой».'),

                Switcher::make(__('moonshine.catalog_import_settings.fields.cache'), 'cache'),

                Number::make(__('moonshine.catalog_import_settings.fields.extra_charge'), 'extra_charge')
                    ->min(0)
                    ->required(),

                Switcher::make(__('moonshine.catalog_import_settings.fields.flag_wu'), 'flag_wu'),
                Switcher::make(__('moonshine.catalog_import_settings.fields.flag_fil'), 'flag_fil'),
                Switcher::make(__('moonshine.catalog_import_settings.fields.flag_mss'), 'flag_mss'),
                Switcher::make(__('moonshine.catalog_import_settings.fields.flag_promo'), 'flag_promo'),

                Preview::make(__('moonshine.catalog_import_settings.fields.last_catalog_update'), 'last_catalog_update'),
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
            'zero_out_missing' => ['boolean'],
            'zero_out_max_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'product_status' => ['required', 'string', Rule::enum(ProductStatus::class)],
            'new_days' => ['required', 'integer', 'min:1'],
            'cache' => ['boolean'],
            'extra_charge' => ['required', 'integer', 'min:0'],
            'flag_wu' => ['boolean'],
            'flag_fil' => ['boolean'],
            'flag_mss' => ['boolean'],
            'flag_promo' => ['boolean'],
        ]);

        $settings = app(CatalogImportSettings::class);
        $settings->alcohol_marker = $data['alcohol_marker'];
        $settings->accessory_marker = $data['accessory_marker'];
        $settings->advent_marker = $data['advent_marker'];
        $settings->fallback_slug = $data['fallback_slug'];
        $settings->zero_out_missing = (bool) ($data['zero_out_missing'] ?? false);
        $settings->zero_out_max_percent = $data['zero_out_max_percent'];
        $settings->product_status = $data['product_status'];
        $settings->new_days = $data['new_days'];
        $settings->cache = (bool) ($data['cache'] ?? false);
        $settings->extra_charge = $data['extra_charge'];
        $settings->product_flags = [
            'wu' => (bool) ($data['flag_wu'] ?? false),
            'fil' => (bool) ($data['flag_fil'] ?? false),
            'mss' => (bool) ($data['flag_mss'] ?? false),
            'promo' => (bool) ($data['flag_promo'] ?? false),
        ];

        $settings->save();

        CategoryRegistry::flush();

        return JsonResponse::make()->toast(__('moonshine.catalog_import_settings.saved'));
    }
}
