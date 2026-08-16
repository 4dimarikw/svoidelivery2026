<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\ContentBlockItem\Pages;

use App\MoonShine\Resources\ContentBlock\ContentBlockResource;
use App\MoonShine\Resources\ContentBlockItem\ContentBlockItemResource;
use App\MoonShine\Traits\ChecksSuperUser;
use Domain\Content\ContentBlockTypeRegistry;
use Domain\Content\Data\ContentItemGroup;
use Domain\Content\Models\ContentBlock;
use Domain\Content\Models\ContentBlockItem;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use VI\MoonShineSpatieMediaLibrary\Fields\MediaLibrary;

/** @extends FormPage<ContentBlockItemResource, ContentBlockItem> */
final class ContentBlockItemFormPage extends FormPage
{
    use ChecksSuperUser;

    protected function fields(): iterable
    {
        $model = $this->getItem();
        $groupOptions = $this->groupOptions();
        $group = $model instanceof ContentBlockItem ? $this->resolveGroup($model) : null;
        $isSuperUser = $this->isSuperUser();

        $fields = [
            Box::make([
                ID::make(),
                BelongsTo::make('Блок', 'block', formatted: static fn (ContentBlock $block) => $block->title, resource: ContentBlockResource::class)
                    ->required()
                    ->readonly(! $isSuperUser),
                Select::make('Группа', 'group_key')->options($groupOptions)->required()->readonly($model instanceof ContentBlockItem),
                Text::make('Ключ', 'key')->required()->canSee(fn () => $isSuperUser),
                Text::make('Название', 'title')->required(),
                Number::make('Порядок', 'sort_order')->default(0)->min(0),
                Switcher::make('Активен', 'is_active')->default(true),
            ]),
        ];

        if ($group !== null) {
            $contentFields = [
                ...array_map(
                    static fn (string $collection) => MediaLibrary::make('Изображение: '.$collection, $collection)->allowedExtensions(['jpg', 'jpeg', 'png', 'webp']),
                    $group->mediaCollections,
                ),
            ];

            if ($group->fields !== []) {
                array_unshift(
                    $contentFields,
                    Json::make('Поля элемента', 'content')->fields($group->fields)->object()->stopFilteringEmpty(),
                );
            }

            if ($contentFields !== []) {
                $fields[] = Box::make('Содержимое', $contentFields);
            }
        }

        return $fields;
    }

    protected function rules(DataWrapperContract $item): array
    {
        $model = $item->getOriginal();
        $blockId = (int) request('content_block_id', $model->content_block_id);
        $groupKey = $model->exists ? $model->group_key : (string) request('group_key');
        $block = ContentBlock::query()->find($blockId);
        $group = $block === null ? null : app(ContentBlockTypeRegistry::class)->get($block->type)?->itemGroups()[$groupKey] ?? null;
        $allowedGroups = $block === null ? [] : array_keys(app(ContentBlockTypeRegistry::class)->get($block->type)?->itemGroups() ?? []);
        $isSuperUser = $this->isSuperUser();
        $rules = [
            'content_block_id' => [$isSuperUser ? 'required' : 'sometimes', 'exists:content_blocks,id'],
            'group_key' => [$model->exists ? 'sometimes' : 'required', Rule::in($allowedGroups)],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'array'],
            'content.icon' => ['nullable', Rule::in(config('content.icons', []))],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];

        if ($isSuperUser) {
            $rules['key'] = [
                'required',
                'alpha_dash',
                'max:255',
                Rule::unique('content_block_items', 'key')
                    ->where(fn ($query) => $query
                        ->where('content_block_id', $blockId)
                        ->where('group_key', $groupKey))
                    ->ignore($item->getKey()),
            ];
        }

        if ($model->exists) {
            foreach ($group?->rules ?? [] as $key => $rule) {
                $rules['content.'.$key] = $rule;
            }
        }

        return $rules;
    }

    private function resolveGroup(ContentBlockItem $item): ?ContentItemGroup
    {
        return app(ContentBlockTypeRegistry::class)
            ->get($item->block?->type)
            ?->itemGroups()[$item->group_key] ?? null;
    }

    /** @return array<string, string> */
    private function groupOptions(): array
    {
        $options = [];

        foreach (app(ContentBlockTypeRegistry::class)->all() as $type) {
            foreach ($type->itemGroups() as $group) {
                $options[$group->key] = $group->label;
            }
        }

        return $options;
    }
}
