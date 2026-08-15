<?php

namespace Domain\Content\Models;

use Database\Factories\Content\ContentBlockFactory;
use Domain\Content\Concerns\HasPublicationState;
use Domain\Content\ContentBlockTypeRegistry;
use Domain\Content\Contracts\ContentBlockType;
use Domain\Content\Observers\ContentBlockObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy(ContentBlockObserver::class)]
class ContentBlock extends Model implements HasMedia
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
        'site_section_id',
        'key',
        'type',
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

    public function section(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class, 'site_section_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContentBlockItem::class)->ordered();
    }

    public function publishedItems(): HasMany
    {
        return $this->hasMany(ContentBlockItem::class)->active()->ordered();
    }

    public function typeDefinition(): ?ContentBlockType
    {
        return app(ContentBlockTypeRegistry::class)->get($this->type);
    }

    public function scopeRenderable(Builder $query): Builder
    {
        return $query->whereIn(
            $query->qualifyColumn('type'),
            array_keys(app(ContentBlockTypeRegistry::class)->all()),
        );
    }

    public function isRenderable(): bool
    {
        return $this->typeDefinition() !== null;
    }

    public function registerMediaCollections(): void
    {
        foreach ($this->typeDefinition()?->mediaCollections() ?? [] as $collection) {
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
        return ContentBlockFactory::new();
    }
}
