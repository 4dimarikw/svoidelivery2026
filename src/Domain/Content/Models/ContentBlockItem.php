<?php

namespace Domain\Content\Models;

use Domain\Content\Models\Concerns\HasPublicationState;
use Domain\Content\Observers\ContentBlockItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy(ContentBlockItemObserver::class)]
class ContentBlockItem extends Model implements HasMedia
{
    use HasFactory;
    use HasPublicationState;
    use InteractsWithMedia;

    protected $attributes = [
        'content' => '[]',
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected $fillable = [
        'content_block_id',
        'group_key',
        'key',
        'title',
        'content',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(ContentBlock::class, 'content_block_id');
    }

    public function registerMediaCollections(): void
    {
        $group = $this->block?->typeDefinition()?->itemGroups()[$this->group_key] ?? null;

        foreach ($group?->mediaCollections ?? [] as $collection) {
            $this->addMediaCollection($collection)->singleFile();
        }
    }
}
