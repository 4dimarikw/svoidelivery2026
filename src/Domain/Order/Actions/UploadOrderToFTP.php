<?php

namespace Domain\Order\Actions;

use App\Events\Order\OrderFtpUploadFailed;
use App\Jobs\UploadOrderToFtpJob;
use Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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
                orderNumber: null, // Order не найден — номера взять неоткуда
                reason: 'order_not_found',
                exceptionClass: $e::class,
                errorMessage: $e->getMessage(),
            ));

            return false;
        }

        try {
            // Сборка XML вынесена в BuildOrderXml — тот же файл используется
            // и как вложение письма-уведомления (HandleOrderCreated::notifyAdmin()).
            $file = app(BuildOrderXml::class)->execute($order);

            // Путь для сохранения на FTP
            $ftpPath = config('order.ftp_upload.dir').'/'.$file->filename;

            // Загружаем файл на FTP с повторными попытками: сбой обычно
            // рвёт только data-канал, поэтому перед повтором соединение
            // сбрасывается принудительно (см. resetFtpConnection()).
            retry(config('order.ftp_upload.max_attempts'), function (int $attempt) use ($ftpPath, $file) {
                if ($attempt > 1) {
                    $this->resetFtpConnection();
                }

                if (! Storage::disk(config('order.ftp_upload.disk'))->put($ftpPath, $file->contents)) {
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
                orderNumber: $order->number,
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
}
