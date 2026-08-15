<?php

namespace Domain\Order\Actions;

use App\Events\Order\OrderFtpUploadFailed;
use App\Jobs\UploadOrderToFtpJob;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderCustomer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class UploadOrderToFTP
{
    /**
     * Создать и загрузить заказ на FTP
     */
    public function __invoke(int $orderId): bool
    {
        return $this->execute($orderId);
    }

    /**
     * Выполнить загрузку заказа на FTP
     *
     * @param  bool  $queueOnFailure  При исчерпании инлайн-попыток поставить
     *                                отложенный UploadOrderToFtpJob. Из самого
     *                                job'а вызывается с false — иначе провал
     *                                последней попытки job'а породил бы новый job.
     */
    public function execute(int $orderId, bool $queueOnFailure = true): bool
    {
        try {
            $order = Order::with(['orderCustomer', 'orderItems.product', 'deliveryType'])
                ->findOrFail($orderId);
        } catch (ModelNotFoundException $e) {
            // Заказа нет — уходить в очередь незачем, там его тоже не найдут.
            report($e);
            event(new OrderFtpUploadFailed(
                orderId: $orderId,
                reason: 'order_not_found',
                exceptionClass: $e::class,
                errorMessage: $e->getMessage(),
            ));

            return false;
        }

        try {
            // Генерируем XML
            $xml = $this->generateOrderXml($order);

            // Формируем имя файла
            $fileName = $this->generateFileName($order);

            // Путь для сохранения на FTP
            $ftpPath = config('order.ftp_upload.dir').'/'.$fileName;

            // Загружаем файл на FTP с повторными попытками: сбой обычно
            // рвёт только data-канал, поэтому перед повтором соединение
            // сбрасывается принудительно (см. resetFtpConnection()).
            retry(config('order.ftp_upload.max_attempts'), function (int $attempt) use ($ftpPath, $xml) {
                if ($attempt > 1) {
                    $this->resetFtpConnection();
                }

                if (! Storage::disk(config('order.ftp_upload.disk'))->put($ftpPath, $xml)) {
                    throw new RuntimeException("FTP put failed: {$ftpPath}");
                }
            }, fn (int $attempt) => $attempt * config('order.ftp_upload.retry_delay_ms'));

            return true;

        } catch (Throwable $e) {
            report($e);

            if ($queueOnFailure) {
                UploadOrderToFtpJob::dispatch($orderId);
            }

            event(new OrderFtpUploadFailed(
                orderId: $orderId,
                reason: 'upload_failed',
                exceptionClass: $e::class,
                errorMessage: $e->getMessage(),
                queued: $queueOnFailure,
            ));

            return false;
        }
    }

    /**
     * Принудительно закрыть и забыть FTP-соединение перед повторной попыткой.
     *
     * FtpAdapter кеширует соединение и считает его живым, пока отвечает
     * управляющий канал (NOOP) — а сбой data-канала на это не влияет.
     * Без явного сброса повтор идёт по той же полусломанной сессии.
     */
    private function resetFtpConnection(): void
    {
        $disk = config('order.ftp_upload.disk');
        $adapter = Storage::disk($disk)->getAdapter();

        // В тестах (Storage::fake()) адаптер локальный, и disconnect() у
        // него нет — метод проверяем заранее, а не полагаемся на try/catch.
        if (method_exists($adapter, 'disconnect')) {
            try {
                $adapter->disconnect();
            } catch (Throwable) {
                // соединение могло быть уже мертво — не мешаем следующей попытке
            }
        }

        Storage::forgetDisk($disk);
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

            // Код товара — у Product нет отдельного SKU-поля, ближайший
            // аналог — article (артикул из 1С).
            $productCode = $orderItem->product?->article ?? '';
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
