<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SiteMenuItem\Pages;

use App\MoonShine\Resources\SiteMenu\SiteMenuResource;
use App\MoonShine\Resources\SiteMenuItem\SiteMenuItemResource;
use App\MoonShine\Resources\SiteSection\SiteSectionResource;
use Domain\Content\Models\SiteMenu;
use Domain\Content\Models\SiteMenuItem;
use Domain\Content\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Http\Requests\Relations\RelationModelFieldRequest;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Url;

/** @extends FormPage<SiteMenuItemResource, SiteMenuItem> */
final class SiteMenuItemFormPage extends FormPage
{
    protected function fields(): iterable
    {
        $model = $this->getItem();
        $editing = $model instanceof SiteMenuItem && $model->exists;
        $excludedParentIds = $editing
            ? $model->newScopedQuery()->descendantsAndSelf($model->getKey(), [$model->getKeyName()])->modelKeys()
            : [];
        $currentMenuId = $editing ? (int)$model->site_menu_id : null;

        $menu = BelongsTo::make('Меню', 'menu', formatted: static fn(SiteMenu $menu) => $menu->title, resource: SiteMenuResource::class)
            ->required();

        if ($editing) {
            $menu->readonly();
        }

        $key = Text::make('Ключ', 'key')->required();

        if ($editing) {
            $key->readonly();
        }

        return [Box::make([
            ID::make(),
            $menu,
            $key,
            BelongsTo::make('Родитель', 'parent', formatted: static fn(SiteMenuItem $item) => $item->label ?: '#' . $item->getKey(), resource: SiteMenuItemResource::class)
                ->nullable()
                ->associatedWith(
                    'site_menu_id',
                    static function (Builder $query, ?string $term, RelationModelFieldRequest $request) use ($currentMenuId, $excludedParentIds): Builder {
                        $menuId = $request->integer('site_menu_id') ?: $currentMenuId;

                        return $query
                            ->where('site_menu_id', $menuId)
                            ->when($excludedParentIds !== [], static fn(Builder $items): Builder => $items->whereNotIn('id', $excludedParentIds))
                            ->when(filled($term), static fn(Builder $items): Builder => $items->where('label', 'like', "%{$term}%"))
                            ->orderBy('_lft');
                    },
                ),
            BelongsTo::make('Раздел', 'section', formatted: static fn(SiteSection $section) => $section->title, resource: SiteSectionResource::class)->nullable()->searchable(),
            Url::make('Внешний URL', 'external_url'),
            Text::make('Подпись (если отличается от названия раздела)', 'label'),
            Switcher::make('Открывать в новой вкладке', 'open_in_new_tab')->default(false),
            Switcher::make('Активен', 'is_active')->default(true),
        ])];
    }

    protected function rules(DataWrapperContract $item): array
    {
        $model = $item->getOriginal();
        $editing = $model instanceof SiteMenuItem && $model->exists;
        $menuId = request()->integer('site_menu_id') ?: (int)$model->site_menu_id;
        $excludedParentIds = $editing
            ? $model->newScopedQuery()->descendantsAndSelf($model->getKey(), [$model->getKeyName()])->modelKeys()
            : [];

        return [
            'site_menu_id' => [$editing ? 'sometimes' : 'required', 'exists:site_menus,id'],
            'key' => [
                $editing ? 'sometimes' : 'required',
                'string',
                'alpha_dash',
                'max:100',
                Rule::unique('site_menu_items', 'key')
                    ->where(static fn($query) => $query->where('site_menu_id', $menuId))
                    ->ignore($editing ? $model->getKey() : null),
            ],
            'parent_id' => [
                'nullable',
                Rule::exists('site_menu_items', 'id')->where(static fn($query) => $query->where('site_menu_id', $menuId)),
                Rule::notIn($excludedParentIds),
            ],
            'site_section_id' => ['nullable', 'exists:site_sections,id'],
            'external_url' => ['nullable', 'url:http,https', 'max:2048'],
            'label' => ['nullable', 'string', 'max:255'],
            'open_in_new_tab' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
