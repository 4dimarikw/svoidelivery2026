<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\EventLog\Pages;

use App\MoonShine\Resources\EventLog\EventLogResource;
use Domain\Logging\Models\EventLog;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\DateRange;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<EventLogResource>
 */
final class EventLogIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Date::make('Время', 'created_at')->format('d.m.Y H:i:s')->sortable(),
            Text::make('Уровень', 'level')->badge(fn (string $level) => match ($level) {
                'error' => 'red',
                'warning' => 'yellow',
                default => 'green',
            }),
            Text::make('Тип события', 'event_type')->sortable(),
            Text::make('Сообщение', formatted: fn (EventLog $item) => Str::limit($item->message, 80)),
            Text::make('Инициатор', formatted: fn (EventLog $item) => $item->causer?->name ?? $item->caused_by_type),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            Select::make('Уровень', 'level')
                ->options(fn () => EventLog::query()->distinct()->orderBy('level')->pluck('level', 'level')->all())
                ->nullable(),
            Select::make('Тип события', 'event_type')
                ->options(fn () => EventLog::query()->distinct()->orderBy('event_type')->pluck('event_type', 'event_type')->all())
                ->nullable(),
            DateRange::make('Период', 'created_at'),
        ];
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        return [
            QueryTag::make('Ошибки', fn (Builder $query) => $query->where('level', 'error')),
            QueryTag::make('Предупреждения', fn (Builder $query) => $query->where('level', 'warning')),
        ];
    }
}
