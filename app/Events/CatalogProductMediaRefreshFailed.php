<?php

namespace App\Events;

/**
 * Ошибка обновления изображения товара регистрируется через PersistEventLog из failed() задания
 * RefreshProductMediaJob. Событие содержит сведения для диагностики сбоя загрузки.
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
