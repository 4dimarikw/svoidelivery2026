<?php

namespace Domain\Content\Models;

use Database\Factories\Content\ContentBlockItemFactory;
use Domain\Content\Concerns\HasPublicationState;
use Domain\Content\Observers\ContentBlockItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\Factory;
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

    /**
     * Модель живёт вне `App\Models` (см. CLAUDE.md, "Domain layer") —
     * дефолтная конвенция фабрик её не резолвит, та же ловушка, что уже
     * задокументирована в Domain\Auth\Models\User::newFactory().
     */
    protected static function newFactory(): Factory
    {
        return ContentBlockItemFactory::new();
    }
}
