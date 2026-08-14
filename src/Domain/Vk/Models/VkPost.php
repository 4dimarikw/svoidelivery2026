<?php

namespace Domain\Vk\Models;

use Domain\Vk\Enums\VkPostStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Пост со стены VK-группы, забранный vk:sync-posts. text — сырой текст
 * из VK, не редактируется в админке; message_text — отредактированная
 * версия для рассылки (broadcastText() отдаёт message_text ?: text).
 */
class VkPost extends Model
{
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
        return Attribute::get(fn (): string => (string) ($this->message_text ?: $this->text));
    }
}
