<?php

namespace Domain\Order\Actions;

/**
 * Результат BuildOrderXml — имя файла и готовый XML заказа. Только
 * скалярные поля: DTO попадает в сериализованный payload очереди вместе
 * с Domain\Order\Mail\NewOrderCreated (см. HandleOrderCreated::notifyAdmin()).
 */
final readonly class OrderXmlFile
{
    public function __construct(
        public string $filename,
        public string $contents,
    ) {}
}
