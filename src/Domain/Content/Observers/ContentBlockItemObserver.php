<?php

namespace Domain\Content\Observers;


use Domain\Content\ContentBlockTypeRegistry;
use Domain\Content\Models\ContentBlockItem;
use Illuminate\Validation\ValidationException;

class ContentBlockItemObserver
{
    public function __construct(private readonly ContentBlockTypeRegistry $types)
    {
    }

    public function creating(ContentBlockItem $item): void
    {
        $this->validateGroup($item);
    }

    public function updating(ContentBlockItem $item): void
    {
        if ($item->isDirty('group_key')) {
            throw ValidationException::withMessages([
                'group_key' => 'Группу элемента нельзя менять после создания.',
            ]);
        }

        $this->validateGroup($item);
    }

    private function validateGroup(ContentBlockItem $item): void
    {
        $type = $this->types->get($item->block()->value('type'));

        if ($type === null || !array_key_exists($item->group_key, $type->itemGroups())) {
            throw ValidationException::withMessages([
                'group_key' => 'Группа не поддерживается типом родительского блока.',
            ]);
        }
    }
}
