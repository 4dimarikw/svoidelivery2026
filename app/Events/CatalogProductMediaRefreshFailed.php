<?php

namespace App\Events;

/**
 * Infrastructure\Jobs\RefreshProductMediaJob не смог перезалить изображение
 * товара — попадает в event_logs через App\Listeners\PersistEventLog
 * (автодискавери по LoggableEvent). Диспатчится из failed() джобы; товар в
 * этот момент уже без старой картинки (clearMediaCollection() выполняется
 * первой) и без новой — событие error-уровня, требует ручного вмешательства.
 */
final readonly class CatalogProductMediaRefreshFailed implements LoggableEvent
{
    public function __construct(
        public int $productId,
        public string $imageUrl,
        public string $exceptionClass,
        public string $errorMessage,
    ) {}

    public function eventType(): string
    {
        return 'catalog.product_media_refresh_failed';
    }

    public function level(): string
    {
        return 'error';
    }

    public function message(): string
    {
        return "Не удалось обновить изображение товара #{$this->productId}: {$this->errorMessage}";
    }

    public function context(): array
    {
        return [
            'product_id' => $this->productId,
            'image_url' => $this->imageUrl,
            'exception_class' => $this->exceptionClass,
            'error_message' => $this->errorMessage,
        ];
    }
}
