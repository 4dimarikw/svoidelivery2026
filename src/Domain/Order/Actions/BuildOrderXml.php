<?php

namespace Domain\Order\Actions;

use Domain\Order\Models\Order;
use Domain\Order\Models\OrderCustomer;
use RuntimeException;
use SimpleXMLElement;

/**
 * Сборка XML заказа для 1С — выделена из UploadOrderToFTP, чтобы тот же
 * файл можно было приложить к письму-уведомлению (см.
 * HandleOrderCreated::notifyAdmin()), а не только заливать на FTP.
 *
 * Ожидает заказ с уже подгруженными orderCustomer/orderItems.product/
 * deliveryType — сам их не грузит (ответственность вызывающего, как и
 * раньше у UploadOrderToFTP::execute()).
 */
class BuildOrderXml
{
    public function __invoke(Order $order): OrderXmlFile
    {
        return $this->execute($order);
    }

    public function execute(Order $order): OrderXmlFile
    {
        return new OrderXmlFile(
            $this->generateFileName($order),
            $this->generateOrderXml($order),
        );
    }

    /**
     * Генерация XML для заказа
     */
    private function generateOrderXml(Order $order): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Orders></Orders>');

        $orderElement = $xml->addChild('Order');

        // Номер заказа
        $this->addTextChild($orderElement, 'OrderNumber', $order->number);

        // Дата заказа
        $orderDate = $order->created_at ? $order->created_at->format('Y-m-d\TH:i:s') : now()->format('Y-m-d\TH:i:s');
        $this->addTextChild($orderElement, 'OrderDate', $orderDate);

        // Информация о клиенте
        $customerElement = $orderElement->addChild('Customer');
        $this->addCustomerData($customerElement, $order->orderCustomer);

        // Товары
        $itemsElement = $orderElement->addChild('Items');
        $this->addOrderItems($itemsElement, $order->orderItems);

        // Комментарий
        $this->addTextChild($orderElement, 'Comment', $this->getComment($order));

        // Форматируем XML с отступами
        return $this->formatXml($xml);
    }

    private function getComment(Order $order): string
    {
        $lines = [
            trim((string) $order->comment),
            'Доставка: '.$order->deliveryType?->title,
        ];

        if ($order->deliveryType?->with_address) {
            $lines[] = 'Город: '.$order->orderCustomer?->city;
            $lines[] = 'Адрес: '.($order->orderCustomer?->address ?? '');
        } else {
            $lines[] = 'Самовывоз';
        }

        return implode("\r\n", array_filter($lines, fn ($line) => $line !== ''));
    }

    /**
     * Добавление данных клиента в XML
     */
    private function addCustomerData(SimpleXMLElement $customerElement, ?OrderCustomer $customer): void
    {
        $name = '';

        if ($customer) {
            $name = trim(($customer->last_name ?? '').' '.($customer->first_name ?? ''));
        }

        $this->addTextChild($customerElement, 'Name', $name);
        $this->addTextChild($customerElement, 'Phone', $customer?->phone ?? '');
    }

    /**
     * Добавление товаров заказа в XML
     */
    private function addOrderItems(SimpleXMLElement $itemsElement, iterable $orderItems): void
    {
        foreach ($orderItems as $orderItem) {
            $itemElement = $itemsElement->addChild('Item');

            // Код товара — external_code, уникальный код номенклатуры из 1С
            // (products.external_code, unique + NOT NULL): по нему 1С находит
            // товар при разборе заказа. article (артикул) для этого не годится —
            // он не уникален и может быть пустым.
            $productCode = $orderItem->product?->external_code ?? '';
            $this->addTextChild($itemElement, 'ProductCode', $productCode);

            $quantity = (int) $orderItem->quantity;
            $this->addTextChild($itemElement, 'Quantity', (string) $quantity);

            $amountValue = number_format($orderItem->amount?->major() ?? 0, 2, '.', '');
            $this->addTextChild($itemElement, 'Price', $amountValue);
        }
    }

    /**
     * Генерация имени файла для заказа
     */
    private function generateFileName(Order $order): string
    {
        return sprintf(
            '%s_%s.xml',
            str_replace(['-', ' '], '_', $order->number),
            $order->created_at?->format('Y-m-d-His') ?? date('Y-m-d-His')
        );
    }

    /**
     * Добавление текстового узла с XML-экранированием значения.
     *
     * SimpleXMLElement::addChild() экранирует "<" сам, но "&" считает
     * началом entity-ссылки — сырой "&" в значении рвёт узел (PHP-warning,
     * узел остаётся пустым). Через этот хелпер обязаны идти все скалярные
     * значения, не только Comment, как было раньше.
     */
    private function addTextChild(SimpleXMLElement $parent, string $name, ?string $value): SimpleXMLElement
    {
        return $parent->addChild($name, htmlspecialchars((string) $value, ENT_XML1, 'UTF-8'));
    }

    /**
     * Форматирование XML с отступами
     */
    private function formatXml(SimpleXMLElement $xml): string
    {
        $dom = dom_import_simplexml($xml)->ownerDocument;

        if ($dom === null) {
            throw new RuntimeException('Не удалось получить DOMDocument из сгенерированного XML заказа');
        }

        $dom->formatOutput = true;

        $result = $dom->saveXML();

        if ($result === false) {
            throw new RuntimeException('saveXML() вернул false при формировании XML заказа');
        }

        return $result;
    }
}
