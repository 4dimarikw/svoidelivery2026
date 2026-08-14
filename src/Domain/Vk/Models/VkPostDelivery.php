<?php

namespace Domain\Vk\Models;

use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Журнал доставки одного VkPost одному пользователю — обеспечивает
 * идемпотентность BroadcastVkPostJob при повторном запуске.
 */
class VkPostDelivery extends Model
{
    protected $fillable = [
        'vk_post_id',
        'user_id',
        'status',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(VkPost::class, 'vk_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
