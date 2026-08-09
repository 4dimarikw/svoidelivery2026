<?php

declare(strict_types=1);

namespace App\MoonShine\Traits;

use App\Models\SiteMenu;
use App\Models\SiteMenuItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\HttpMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\ActionButton;

trait MoveItemButtons
{
    /** @return list<ActionButton> */
    public function itemMoveButtons(): array
    {
        $events = [AlpineJs::event(JsEvent::TABLE_UPDATED, $this->getListComponentName())];

        return [
            ActionButton::make('')
                ->icon('chevron-up')
                ->showInLine()
                ->square()
                ->method(
                    'itemMove',
                    params: ['action' => 'up'],
                    events: $events,
                )
                ->async(HttpMethod::PATCH, events: $events)
                ->setAttribute('data-async-method', HttpMethod::PATCH->value),
            ActionButton::make('')
                ->icon('chevron-down')
                ->showInLine()
                ->square()
                ->method(
                    'itemMove',
                    params: ['action' => 'down'],
                    events: $events,
                )
                ->async(HttpMethod::PATCH, events: $events)
                ->setAttribute('data-async-method', HttpMethod::PATCH->value),
        ];
    }

    #[AsyncMethod]
    public function itemMove(CrudRequestContract $request): JsonResponse
    {
        if (! $request->isMethod('patch')) {
            throw ValidationException::withMessages([
                'action' => 'Для изменения порядка требуется PATCH-запрос.',
            ]);
        }

        $resource = $request->getResource();
        abort_unless($resource->can(Ability::UPDATE), 403);

        $item = $resource->getItem();
        if (! $item instanceof SiteMenuItem) {
            throw ValidationException::withMessages([
                'action' => 'Не удалось определить пункт меню.',
            ]);
        }

        $action = (string) $request->get('action');
        if (! in_array($action, ['up', 'down'], true)) {
            throw ValidationException::withMessages([
                'action' => 'Разрешено перемещение только вверх или вниз.',
            ]);
        }

        $moved = DB::transaction(function () use ($action, $item): bool {
            SiteMenu::query()->whereKey($item->site_menu_id)->lockForUpdate()->firstOrFail();
            $item->refresh();

            return match ($action) {
                'up' => $item->up(),
                'down' => $item->down(),
            };
        }, 3);

        $label = trim((string) $item->label) ?: '#'.$item->getKey();

        return JsonResponse::make()->toast(
            $moved ? "Порядок пункта «{$label}» изменён." : "Пункт «{$label}» уже находится на границе списка.",
            $moved ? ToastType::SUCCESS : ToastType::INFO,
        );
    }
}
