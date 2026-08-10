<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\SiteMenuItem\Pages;

use App\MoonShine\Resources\SiteMenu\SiteMenuResource;
use App\MoonShine\Resources\SiteMenuItem\SiteMenuItemResource;
use App\MoonShine\Resources\SiteSection\SiteSectionResource;
use App\MoonShine\Traits\MoveItemButtons;
use Domain\Content\Actions\Menu\MoveSiteMenuItemBranch;
use Domain\Content\Models\SiteMenu;
use Domain\Content\Models\SiteMenuItem;
use Domain\Content\Models\SiteSection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\HttpMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Url;

/** @extends IndexPage<SiteMenuItemResource> */
final class SiteMenuItemIndexPage extends IndexPage
{
    use MoveItemButtons;

    protected function fields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make('Меню', 'menu', formatted: static fn (SiteMenu $menu) => $menu->title, resource: SiteMenuResource::class),
            Text::make('Ключ', 'key'),
            BelongsTo::make(
                'Родитель',
                'parent',
                // При parent_id = null MoonShine всё равно вызывает formatted-колбэк,
                // подставляя `$relation->getModel()` (ModelRelationField::toFormattedValue).
                // У nestedset-отношения parent() (`->setModel($this)`) это сама текущая
                // строка, поэтому у корневого пункта в колонке отрисовывался бы его
                // собственный label — отсюда явная проверка настоящего значения поля.
                formatted: static fn (?SiteMenuItem $item, int $index, BelongsTo $field): string => $field->toValue(withDefault: false) === null
                    ? ''
                    : ($item->label ?: '#'.$item->getKey()),
                resource: SiteMenuItemResource::class,
            ),
            Text::make('Подпись', 'label'),
            BelongsTo::make('Раздел', 'section', formatted: static fn (SiteSection $section) => $section->title, resource: SiteSectionResource::class),
            Url::make('Внешний URL', 'external_url'),
            Switcher::make('Активен', 'is_active'),
        ];
    }

    protected function filters(): iterable
    {
        return [
            BelongsTo::make('Меню', 'menu', formatted: static fn (SiteMenu $menu) => $menu->title, resource: SiteMenuResource::class),
            BelongsTo::make('Раздел', 'section', formatted: static fn (SiteSection $section) => $section->title, resource: SiteSectionResource::class),
            Switcher::make('Активен', 'is_active'),
        ];
    }

    protected function buttons(): ListOf
    {
        return parent::buttons()->add(...[
            ...$this->itemMoveButtons(),
            $this->transferButton(),
        ]);
    }

    protected function modifyMassDeleteButton(ActionButtonContract $button): ActionButtonContract
    {
        return $button->canSee(static fn (): bool => false);
    }

    #[AsyncMethod]
    public function transferItem(
        CrudRequestContract $request,
        MoveSiteMenuItemBranch $moveBranch,
    ): JsonResponse {
        if (! $request->isMethod('patch')) {
            throw ValidationException::withMessages([
                'target_menu_id' => 'Для переноса требуется PATCH-запрос.',
            ]);
        }

        $resource = $request->getResource();
        abort_unless($resource->can(Ability::UPDATE), 403);

        $item = $resource->getItem();
        if (! $item instanceof SiteMenuItem) {
            throw ValidationException::withMessages([
                'target_menu_id' => 'Не удалось определить пункт меню.',
            ]);
        }

        $targetMenuId = $request->integer('target_menu_id');
        $validated = $request->validate([
            'target_menu_id' => [
                'required',
                'integer',
                Rule::exists('site_menus', 'id'),
                Rule::notIn([(int) $item->site_menu_id]),
            ],
            'target_parent_id' => [
                'nullable',
                'integer',
                Rule::exists('site_menu_items', 'id')->where(
                    static fn ($query) => $query->where('site_menu_id', $targetMenuId),
                ),
            ],
        ]);

        $targetMenu = SiteMenu::query()->findOrFail($validated['target_menu_id']);
        $targetParent = filled($validated['target_parent_id'] ?? null)
            ? SiteMenuItem::query()->findOrFail($validated['target_parent_id'])
            : null;

        $moved = $moveBranch->handle($item, $targetMenu, $targetParent);
        $label = trim((string) $moved->label) ?: '#'.$moved->getKey();

        return JsonResponse::make()->toast("Ветка «{$label}» перенесена.");
    }

    private function transferButton(): ActionButton
    {
        $events = [AlpineJs::event(JsEvent::TABLE_UPDATED, $this->getListComponentName())];

        return ActionButton::make('')
            ->icon('arrow-right-circle')
            ->showInLine()
            ->square()
            ->method('transferItem', events: $events)
            ->async(HttpMethod::PATCH, events: $events)
            ->withConfirm(
                title: 'Перенести ветку',
                content: 'Пункт и все вложенные пункты будут перенесены вместе.',
                button: 'Перенести',
                fields: fn (SiteMenuItem $item): array => $this->transferFields($item),
                method: HttpMethod::PATCH,
            );
    }

    /** @return list<Select> */
    private function transferFields(SiteMenuItem $item): array
    {
        $menus = SiteMenu::query()
            ->whereKeyNot($item->site_menu_id)
            ->ordered()
            ->pluck('title', 'id')
            ->all();

        $parents = SiteMenuItem::query()
            ->with('menu')
            ->where('site_menu_id', '!=', $item->site_menu_id)
            ->orderBy('site_menu_id')
            ->orderBy('_lft')
            ->get()
            ->mapWithKeys(static fn (SiteMenuItem $parent): array => [
                $parent->getKey() => '['.$parent->menu->title.'] '.(trim((string) $parent->label) ?: '#'.$parent->getKey()),
            ])
            ->all();

        return [
            Select::make('Целевое меню', 'target_menu_id')->options($menus)->required()->searchable(),
            Select::make('Родитель в целевом меню', 'target_parent_id')->options($parents)->nullable()->searchable(),
        ];
    }
}
