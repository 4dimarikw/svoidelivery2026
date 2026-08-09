<?php

namespace Domain\Content\Observers;

use App\Content\ContentBlockTypeRegistry;
use App\Models\ContentBlock;
use Illuminate\Validation\ValidationException;

class ContentBlockObserver
{
    public function __construct(private readonly ContentBlockTypeRegistry $types) {}

    public function creating(ContentBlock $block): void
    {
        if (! $this->types->has($block->type)) {
            throw ValidationException::withMessages([
                'type' => 'Выбран неизвестный тип блока.',
            ]);
        }
    }

    public function updating(ContentBlock $block): void
    {
        if ($block->isDirty('type')) {
            throw ValidationException::withMessages([
                'type' => 'Тип блока нельзя менять после создания.',
            ]);
        }
    }

    public function deleting(ContentBlock $block): void
    {
        $block->items()->eachById(static fn ($item) => $item->delete());
    }
}
