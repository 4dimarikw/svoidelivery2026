<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Enums\LoggableEventType;

final readonly class VkPostTestSend implements LoggableEvent
{
    public function __construct(
        public int $vkPostId,
        public string $result,
        public ?string $error = null,
        public string $imageSendMode = 'original_url',
        public array $imageUrls = [],
    ) {}

    public function eventType(): string
    {
        return LoggableEventType::VkPostTestSend->value;
    }

    public function level(): string
    {
        return match ($this->result) {
            'sent' => 'info',
            'skipped' => 'warning',
            default => 'error',
        };
    }

    public function message(): string
    {
        return match ($this->result) {
            'sent' => "Тестовое сообщение поста VK #{$this->vkPostId} отправлено.",
            'skipped' => "Тестовое сообщение поста VK #{$this->vkPostId} не отправлено: {$this->error}",
            default => "Ошибка тестовой отправки поста VK #{$this->vkPostId}: {$this->error}",
        };
    }

    public function context(): array
    {
        return [
            'vk_post_id' => $this->vkPostId,
            'result' => $this->result,
            'error' => $this->error,
            'image_send_mode' => $this->imageSendMode,
            'image_urls' => $this->imageUrls,
        ];
    }
}
