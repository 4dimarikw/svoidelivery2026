<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\EventLog;

use App\MoonShine\Resources\EventLog\Pages\EventLogDetailPage;
use App\MoonShine\Resources\EventLog\Pages\EventLogIndexPage;
use Domain\Logging\Models\EventLog;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\ListOf;

/**
 * Только просмотр — EventLog immutable (Domain\Logging\Models\EventLog::booted(),
 * updating кидает LogicException), а строки пишет исключительно
 * App\Listeners\PersistEventLog. Поэтому CREATE/UPDATE/DELETE/MASS_DELETE
 * отключены и FormPage вообще нет — см. pages() ниже.
 *
 * @extends ModelResource<EventLog, EventLogIndexPage, null, EventLogDetailPage>
 */
#[Icon('list-bullet')]
#[Group('Система', 'users')]
#[Order(10)]
class EventLogResource extends ModelResource
{
    protected string $model = EventLog::class;

    protected bool $withPolicy = true;

    protected string $column = 'message';

    protected string $sortColumn = 'created_at';

    protected SortDirection $sortDirection = SortDirection::DESC;

    protected array $with = ['causer'];

    public function getTitle(): string
    {
        return __('moonshine.event_log.title');
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::CREATE, Action::UPDATE, Action::DELETE, Action::MASS_DELETE);
    }

    protected function pages(): array
    {
        return [
            EventLogIndexPage::class,
            EventLogDetailPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'event_type', 'message'];
    }
}
