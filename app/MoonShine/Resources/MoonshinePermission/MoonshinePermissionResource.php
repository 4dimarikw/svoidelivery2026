<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MoonshinePermission;

use App\MoonShine\Models\MoonshinePermission;
use App\MoonShine\Resources\MoonshinePermission\Pages\MoonshinePermissionDetailPage;
use App\MoonShine\Resources\MoonshinePermission\Pages\MoonshinePermissionFormPage;
use App\MoonShine\Resources\MoonshinePermission\Pages\MoonshinePermissionIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\ExportHandler;
use MoonShine\ImportExport\ImportHandler;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<MoonshinePermission, MoonshinePermissionIndexPage, MoonshinePermissionFormPage, MoonshinePermissionDetailPage>
 */
#[Icon('lock-closed')]
#[Group('moonshine::ui.resource.system', 'users', translatable: true)]
#[Order(2)]
class MoonshinePermissionResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model = MoonshinePermission::class;

    protected bool $withPolicy = true;

    protected string $title = 'Права доступа';

    protected string $column = 'model';

    protected bool $createInModal = true;

    protected bool $editInModal = true;

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            MoonshinePermissionIndexPage::class,
            MoonshinePermissionFormPage::class,
            MoonshinePermissionDetailPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'model'];
    }

    /**
     * @return list<FieldContract>
     */
    protected function importFields(): iterable
    {
        return [
            ID::make('id'),
            Text::make('moonshine_user_role_id'),
            Text::make('model'),
            Json::make('permissions'),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function exportFields(): iterable
    {
        return [
            ...$this->importFields(),
        ];
    }

    protected function import(): ?Handler
    {
        return ImportHandler::make(__('moonshine::ui.import'))
            ->notifyUsers(static fn (): array => [auth()->id()])
            ->disk('public')
            ->dir('/imports')
            ->deleteAfter()
            ->delimiter(';');
    }

    protected function export(): ?Handler
    {
        return ExportHandler::make(__('moonshine::ui.export'))
            ->notifyUsers(static fn (): array => [auth()->id()])
            ->disk('public')
            ->filename(sprintf('moonshine_permissions_export_%s', date('Ymd-His')))
            ->dir('/exports')
            ->delimiter(';');
    }
}
