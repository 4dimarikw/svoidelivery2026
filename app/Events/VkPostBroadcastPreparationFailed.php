<?php

declare(strict_types=1);

namespace App\Events;

final readonly class VkPostBroadcastPreparationFailed implements LoggableEvent
{
    public function __construct(public int $vkPostId, public string $error) {}

    public function eventType(): string
    {
        return 'vk_post.broadcast_preparation_failed';
    }

    public function level(): string
    {
        return 'error';
    }

    public function message(): string
    {
        return "Рассылка поста VK #{$this->vkPostId} не начата: {$this->error}";
    }

    public function context(): array
    {
        return ['vk_post_id' => $this->vkPostId, 'error' => $this->error];
    }
}
