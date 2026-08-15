<?php

namespace Domain\Vk\Models;

use Database\Factories\Vk\VkPostDeliveryFactory;
use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Журнал доставки одного VkPost одному пользователю — обеспечивает
 * идемпотентность BroadcastVkPostJob при повторном запуске.
 */
class VkPostDelivery extends Model
{
    use HasFactory;

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

    /**
     * Модель живёт вне `App\Models` (см. CLAUDE.md, "Domain layer") —
     * дефолтная конвенция фабрик её не резолвит, та же ловушка, что уже
     * задокументирована в Domain\Auth\Models\User::newFactory().
     */
    protected static function newFactory(): Factory
    {
        return VkPostDeliveryFactory::new();
    }
}
