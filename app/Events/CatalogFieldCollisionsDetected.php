<?php

namespace App\Events;

final readonly class CatalogFieldCollisionsDetected implements LoggableEvent
{
    /** @param list<array<string, mixed>> $collisions */
    public function __construct(public string $path, public int $count, public array $collisions) {}

    public function eventType(): string
    {
        return 'catalog_import.field_collisions_detected';
    }

    public function level(): string
    {
        return 'warning';
    }

    public function message(): string
    {
        return sprintf('Обнаружено %d коллизий полей товара; не сохранено подробностей: %d.', $this->count, $this->count - count($this->collisions));
    }

    public function context(): array
    {
        return [
            'path' => $this->path,
            'count' => $this->count,
            'omitted_count' => $this->count - count($this->collisions),
            'collisions' => $this->collisions,
        ];
    }
}
