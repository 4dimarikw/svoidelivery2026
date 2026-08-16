<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\EventLog\Pages;

use App\MoonShine\Resources\EventLog\EventLogResource;
use Domain\Logging\Models\EventLog;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends DetailPage<EventLogResource>
 */
class EventLogDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Date::make('Время', 'created_at')->withTime(),
            Text::make('Уровень', 'level'),
            Text::make('Тип события', 'event_type'),
            Textarea::make('Сообщение', 'message'),
            // Контексты не единообразно плоские (у CatalogImportCompleted
            // внутри вложенный список warnings из {line,stage,message,value}),
            // поэтому обычный key/value-виджет тут отрисует плохо —
            // читаемый pretty-printed JSON надёжнее.
            Textarea::make('Контекст', formatted: fn(EventLog $item) => json_encode(
                $item->context,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )),
            Preview::make('Контекст', formatted: fn(EventLog $item) => json_encode(
                $item->context,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
            ),
            Text::make('Инициатор', formatted: fn(EventLog $item) => $item->causer?->name ?? '—'),
            Text::make('Источник', 'caused_by_type'),
        ];
    }
}
