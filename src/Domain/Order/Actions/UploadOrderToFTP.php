<?php

namespace Domain\Order\Actions;

use App\Jobs\UploadOrderToFtpJob;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderCustomer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class UploadOrderToFTP
{
    /**
     * Директория на FTP для загрузки заказов
     */
    private const string FTP_ORDERS_DIR = 'Orders'; // Orders

    /**
     * Количество инлайн-попыток загрузки на FTP перед уходом в очередь
     */
    private const int MAX_ATTEMPTS = 3;

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
            // Получаем заказ с необходимыми связями
            $order = Order::with(['orderCustomer', 'orderItems.product'])
                ->findOrFail($orderId);

            // Генерируем XML
            $xml = $this->generateOrderXml($order);

            // Формируем имя файла
            $fileName = $this->generateFileName($order);

            // Путь для сохранения на FTP
            $ftpPath = self::FTP_ORDERS_DIR.'/'.$fileName;

            // Загружаем файл на FTP с повторными попытками: сбой обычно
            // рвёт только data-канал, поэтому перед повтором соединение
            // сбрасывается принудительно (см. resetFtpConnection()).
            retry(self::MAX_ATTEMPTS, function (int $attempt) use ($ftpPath, $xml) {
                if ($attempt > 1) {
                    $this->resetFtpConnection();
                }

                if (! Storage::disk('ftp')->put($ftpPath, $xml)) {
                    throw new RuntimeException("FTP put failed: {$ftpPath}");
                }
            }, fn (int $attempt) => $attempt * 1000);

            return true;

        } catch (Throwable $e) {
            report($e);
            Log::warning('Не удалось загрузить заказ на FTP', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toDateTimeString(),
            ]);

            if ($queueOnFailure) {
                UploadOrderToFtpJob::dispatch($orderId);
            }

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
        try {
            Storage::disk('ftp')->getAdapter()->disconnect();
        } catch (Throwable) {
            // соединение могло быть уже мертво — не мешаем следующей попытке
        }

        Storage::forgetDisk('ftp');
    }

    /**
     * Генерация XML для заказа
     */
    private function generateOrderXml(Order $order): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Orders></Orders>');

        $orderElement = $xml->addChild('Order');

        // Номер заказа
        $orderElement->addChild('OrderNumber', $order->number);

        // Дата заказа
        $orderDate = $order->created_at ? $order->created_at->format('Y-m-d\TH:i:s') : now()->format('Y-m-d\TH:i:s');
        $orderElement->addChild('OrderDate', $orderDate);

        // Информация о клиенте
        $customerElement = $orderElement->addChild('Customer');
        $this->addCustomerData($customerElement, $order->orderCustomer);

        // Товары
        $itemsElement = $orderElement->addChild('Items');
        $this->addOrderItems($itemsElement, $order->orderItems);

        // Комментарий
        $orderElement->addChild('Comment', htmlspecialchars($this->getComment($order), ENT_XML1, 'UTF-8'));

        // Форматируем XML с отступами
        return $this->formatXml($xml);
    }

    private function getComment(Order $order): string
    {
        $comment = ($order->comment ?? '')."\r\n";
        $comment .= 'Доставка: '.$order->deliveryType->title."\r\n";

        if ($order->deliveryType->with_address) {
            $comment .= 'Город: '.$order->orderCustomer?->city."\r\n";
            $comment .= 'Адрес: '.($order->orderCustomer?->address ?? '');
        } else {
            $comment .= 'Самовывоз';
        }

        return $comment;
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

        $customerElement->addChild('Name', $name);
        $customerElement->addChild('Phone', $customer?->phone ?? '');
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
            $itemElement->addChild('ProductCode', $productCode);

            $quantity = (int) $orderItem->quantity;
            $itemElement->addChild('Quantity', $quantity);

            $amountValue = number_format($orderItem->amount->major(), 2, '.', '');
            $itemElement->addChild('Price', $amountValue);
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
     * Форматирование XML с отступами
     */
    private function formatXml(SimpleXMLElement $xml): string
    {
        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
    }
}
