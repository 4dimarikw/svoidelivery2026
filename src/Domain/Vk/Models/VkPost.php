<?php

namespace Domain\Vk\Models;

use Database\Factories\Vk\VkPostFactory;
use Domain\Vk\Enums\VkPostStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Пост со стены VK-группы, забранный vk:sync-posts. text — сырой текст
 * из VK, не редактируется в админке; message_text — отредактированная
 * версия для рассылки, засеивается текстом с HTML-ссылками только при создании поста
 * синхронизацией, дальше живёт исключительно под редактором — повторный
 * vk:sync-posts его не трогает (как и status) даже у поста в статусе
 * draft. Пустой message_text значит «рассылать нечего» — broadcastText()
 * не подставляет вместо него сырой text (см. гарды на
 * SendVkPostToChatAction / BroadcastVkPostJob).
 */
class VkPost extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    public const string IMAGE_COLLECTION = 'vk_images';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGE_COLLECTION)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function localImage(string $url): ?Media
    {
        return $this->getMedia(self::IMAGE_COLLECTION)->first(
            fn (Media $media) => $media->getCustomProperty('source_hash') === hash('sha256', $url)
                && Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())
        );
    }

    /** @return list<string> */
    public function imagePreviewUrls(): array
    {
        return array_map(fn (string $url) => $this->localImage($url)?->getUrl() ?? $url, $this->images ?? []);
    }

    /** @return list<string> */
    public function localImageUrls(): array
    {
        return array_values(array_filter(array_map(fn (string $url) => $this->localImage($url)?->getUrl(), $this->images ?? [])));
    }

    public function imageDownloadStatus(): string
    {
        return count($this->localImageUrls()).' / '.count($this->images ?? []);
    }

    protected $fillable = [
        'owner_id',
        'vk_post_id',
        'post_type',
        'posted_at',
        'text',
        'message_text',
        'images',
        'rejected_images',
        'raw',
        'status',
        'broadcast_at',
        'broadcast_stats',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'broadcast_at' => 'datetime',
            'images' => 'array',
            'rejected_images' => 'array',
            'raw' => 'array',
            'broadcast_stats' => 'array',
            'status' => VkPostStatus::class,
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(VkPostDelivery::class);
    }

    protected function vkUrl(): Attribute
    {
        return Attribute::get(fn (): string => "https://vk.com/wall{$this->owner_id}_{$this->vk_post_id}");
    }

    protected function broadcastText(): Attribute
    {
        return Attribute::get(fn (): string => (string) $this->message_text);
    }

    /**
     * Модель живёт вне `App\Models` (см. CLAUDE.md, "Domain layer") —
     * дефолтная конвенция фабрик её не резолвит, та же ловушка, что уже
     * задокументирована в Domain\Auth\Models\User::newFactory().
     */
    protected static function newFactory(): Factory
    {
        return VkPostFactory::new();
    }
}
