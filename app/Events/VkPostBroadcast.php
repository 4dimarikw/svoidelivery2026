<?php

namespace App\Events;

/**
 * Итог BroadcastVkPostJob — попадает в event_logs через
 * App\Listeners\PersistEventLog (автодискавери по LoggableEvent, как и
 * CatalogStaleProductsZeroed).
 */
final readonly class VkPostBroadcast implements LoggableEvent
{
    public function __construct(
        public int $vkPostId,
        public int $sent,
        public int $failed,
        public int $skipped,
    ) {}

    public function eventType(): string
    {
        return 'vk_post.broadcast';
    }

    public function level(): string
    {
        return $this->failed > 0 ? 'warning' : 'info';
    }

    public function message(): string
    {
        return "Рассылка поста VK #{$this->vkPostId}: отправлено {$this->sent}, ошибок {$this->failed}, пропущено {$this->skipped}.";
    }

    public function context(): array
    {
        return [
            'vk_post_id' => $this->vkPostId,
            'sent' => $this->sent,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
        ];
    }
}
