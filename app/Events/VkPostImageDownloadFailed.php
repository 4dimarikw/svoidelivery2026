<?php

declare(strict_types=1);

namespace App\Events;

final readonly class VkPostImageDownloadFailed implements LoggableEvent
{
    public function __construct(public int $vkPostId, public string $sourceUrl, public string $error) {}

    public function eventType(): string
    {
        return 'vk_post.image_download_failed';
    }

    public function level(): string
    {
        return 'error';
    }

    public function message(): string
    {
        return "Ошибка загрузки картинки поста VK #{$this->vkPostId}: {$this->error}";
    }

    public function context(): array
    {
        // Query-параметры CDN могут содержать временные подписи доступа.
        return ['vk_post_id' => $this->vkPostId, 'image_url' => strtok($this->sourceUrl, '?'),
            'source_hash' => hash('sha256', $this->sourceUrl), 'error' => $this->error];
    }
}
