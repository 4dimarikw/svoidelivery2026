<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ContentBlock;


use App\MoonShine\Resources\ContentBlock\Pages\ContentBlockFormPage;
use App\MoonShine\Resources\ContentBlock\Pages\ContentBlockIndexPage;
use Domain\Content\Models\ContentBlock;
use MoonShine\Crud\Handlers\Handler;
use MoonShine\ImportExport\Contracts\HasImportExportContract;
use MoonShine\ImportExport\ExportHandler;
use MoonShine\ImportExport\ImportHandler;
use MoonShine\ImportExport\Traits\ImportExportConcern;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\UI\Fields\ID;

/** @extends ModelResource<ContentBlock, ContentBlockIndexPage, ContentBlockFormPage, null> */
#[Icon('rectangle-stack')]
#[Group('Контент', 'document-text')]
#[Order(40)]
final class ContentBlockResource extends ModelResource implements HasImportExportContract
{
    use ImportExportConcern;

    protected string $model = ContentBlock::class;

    protected string $column = 'title';

    protected array $with = ['section', 'items'];

    protected string $sortColumn = 'sort_order';

    protected SortDirection $sortDirection = SortDirection::ASC;

    public function getTitle(): string
    {
        return 'Блоки';
    }

    protected function pages(): array
    {
        return [ContentBlockIndexPage::class, ContentBlockFormPage::class];
    }

    protected function search(): array
    {
        return ['id', 'key', 'title', 'type', 'content'];
    }

    protected function importFields(): iterable
    {
        return [
            ID::make(),
//            Number::make('ID раздела', 'site_section_id')->required(),
//            Text::make('Ключ', 'key')->required(),
//            Text::make('Тип', 'type')->required(),
//            Text::make('Название', 'title')->required(),
////            Json::make('Содержимое', 'content')->nullable(),
//            Number::make('Порядок', 'sort_order')->default(0)->min(0),
//            Switcher::make('Активен', 'is_active')->default(true),
        ];
    }

    protected function exportFields(): iterable
    {
        return [
            ...$this->importFields(),
        ];
    }

    protected function import(): ?Handler
    {
        return ImportHandler::make(__('moonshine::ui.import'))
            ->notifyUsers(fn(ImportHandler $ctx) => [auth()->id()])
            ->disk('public')
            ->dir('/imports')
            ->deleteAfter();
    }

    protected function export(): ?Handler
    {
        return ExportHandler::make(__('moonshine::ui.export'))
            ->notifyUsers(fn() => [auth()->id()])
            ->disk('public')
            ->filename(sprintf('content_block_export_%s', date('Ymd-His')))
            ->dir('/exports');
    }
}
