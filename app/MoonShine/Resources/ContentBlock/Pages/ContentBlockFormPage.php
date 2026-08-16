<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ContentBlock\Pages;

use App\MoonShine\Resources\ContentBlock\ContentBlockResource;
use App\MoonShine\Resources\ContentBlockItem\ContentBlockItemResource;
use App\MoonShine\Resources\SiteSection\SiteSectionResource;
use Domain\Content\ContentBlockTypeRegistry;
use Domain\Content\Models\ContentBlock;
use Domain\Content\Models\SiteSection;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use VI\MoonShineSpatieMediaLibrary\Fields\MediaLibrary;

/** @extends FormPage<ContentBlockResource, ContentBlock> */
final class ContentBlockFormPage extends FormPage
{
    protected function fields(): iterable
    {
        $registry = app(ContentBlockTypeRegistry::class);
        $model = $this->getItem();
        $type = $model instanceof ContentBlock ? $registry->get($model->type) : null;
        $typeOptions = $registry->options();

        if ($model instanceof ContentBlock && $type === null) {
            $typeOptions[$model->type] = '⚠ Неизвестный тип: ' . $model->type;
        }

        $fields = [
            Box::make([
                ID::make(),
                BelongsTo::make('Раздел', 'section', formatted: static fn(SiteSection $section) => $section->title, resource: SiteSectionResource::class)->required(),
                Select::make('Тип', 'type')->options($typeOptions)->required()->readonly($model instanceof ContentBlock),
                Text::make('Ключ', 'key')->required(),
                Text::make('Название', 'title')->required(),
                Number::make('Порядок', 'sort_order')->default(0)->min(0),
                Switcher::make('Активен', 'is_active')->default(true),
            ]),
        ];

        if ($type !== null) {
            $fields[] = Box::make('Содержимое', [
                Json::make('Поля блока', 'content')->fields($type->blockFields())->object()->stopFilteringEmpty(),
//                ...array_map(
//                    static fn (string $collection) => MediaLibrary::make('Изображение: '.$collection, $collection)->allowedExtensions(['jpg', 'jpeg', 'png', 'webp']),
//                    $type->mediaCollections(),
//                ),
                ...array_map(
                    static fn(string $collection) => MediaLibrary::make('Изображение: ' . $collection, $collection)
                        ->keepOriginalFileName()
                        ->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])
                        ->dir('upload_for_media_library')
                        ->itemAttributes(fn(string $filename, int $index = 0) => [
                            'style' => 'width: 100%; max-width: 500px; height: 100%;',
                        ])
                        ->removable(),
                    $type->mediaCollections(),
                ),
            ]);
            $fields[] = HasMany::make('Элементы блока', 'items', resource: ContentBlockItemResource::class)->creatable();
        }

        return $fields;
    }

    protected function rules(DataWrapperContract $item): array
    {
        $registry = app(ContentBlockTypeRegistry::class);
        $model = $item->getOriginal();
        $typeKey = $model->exists ? $model->type : (string)request('type');
        $type = $registry->get($typeKey);
        $rules = [
            'site_section_id' => ['required', 'exists:site_sections,id'],
            'type' => [$model->exists ? 'sometimes' : 'required', Rule::in(array_keys($registry->options()))],
            'key' => [
                'required',
                'alpha_dash',
                'max:255',
                Rule::unique('content_blocks', 'key')
                    ->where(fn($query) => $query->where('site_section_id', request('site_section_id')))
                    ->ignore($item->getKey()),
            ],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'array'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];

        foreach ($type?->blockRules() ?? [] as $key => $rule) {
            $rules['content.' . $key] = $rule;
        }

        return $rules;
    }
}
