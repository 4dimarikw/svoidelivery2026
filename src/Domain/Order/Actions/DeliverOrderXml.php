<?php

declare(strict_types=1);

namespace Domain\Order\Actions;

use App\Events\Order\OrderFtpUploadFailed;
use Domain\Order\Models\OrderDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class DeliverOrderXml
{
    public function execute(int $deliveryId): void
    {
        if (! config('order.ftp_upload.outbox_enabled', true)) {
            return;
        }
        $delivery = DB::transaction(function () use ($deliveryId): ?OrderDelivery {
            $delivery = OrderDelivery::query()->whereKey($deliveryId)->due()->lockForUpdate()->first();
            if (! $delivery) {
                return null;
            }
            // Истёкшая блокировка означает неопределённый результат прошлой попытки.
            if ($delivery->cycle_attempts >= 5) {
                $delivery->update([
                    'state' => 'failed', 'lock_token' => null, 'locked_until' => null,
                    'next_attempt_at' => null, 'last_error' => 'Исчерпаны попытки; результат последней загрузки неизвестен.',
                ]);

                return null;
            }
            $delivery->update([
                'state' => 'processing', 'attempts' => $delivery->attempts + 1,
                'cycle_attempts' => $delivery->cycle_attempts + 1,
                'lock_token' => (string) Str::uuid(), 'locked_until' => now()->addSeconds(180),
                'next_attempt_at' => null,
            ]);

            return $delivery;
        });
        if (! $delivery) {
            return;
        }

        try {
            // Каждая попытка получает свежее соединение, без вложенных сетевых повторов.
            $adapter = Storage::disk($delivery->disk)->getAdapter();
            if (method_exists($adapter, 'disconnect')) {
                $adapter->disconnect();
                Storage::forgetDisk($delivery->disk);
            }
            if (! Storage::disk($delivery->disk)->put($delivery->ftp_path, $delivery->xml)) {
                throw new RuntimeException('FTP put failed');
            }
        } catch (Throwable $e) {
            // Текст исключения может содержать XML, адрес, пароль или данные клиента.
            $error = 'Ошибка загрузки FTP ('.$e::class.').';
            $retry = $delivery->cycle_attempts < 5;
            $updated = $this->owned($delivery)->update([
                'state' => $retry ? 'retry' : 'failed', 'last_error' => $error,
                'next_attempt_at' => $retry ? now()->addSeconds([60, 120, 300, 600][$delivery->cycle_attempts - 1]) : null,
                'lock_token' => null, 'locked_until' => null, 'updated_at' => now(),
            ]);
            if ($updated) {
                event(new OrderFtpUploadFailed(
                    orderId: (int) $delivery->order_id, orderNumber: null,
                    reason: $retry ? 'upload_failed' : 'queue_exhausted',
                    exceptionClass: $e::class, errorMessage: $error, queued: $retry,
                ));
            }

            return;
        } finally {
            // Закрытие соединения не должно менять результат загрузки.
            try {
                $adapter = Storage::disk($delivery->disk)->getAdapter();
                if (method_exists($adapter, 'disconnect')) {
                    $adapter->disconnect();
                    Storage::forgetDisk($delivery->disk);
                }
            } catch (Throwable) {
            }
        }

        $this->owned($delivery)->update([
            'state' => 'sent', 'sent_at' => now(), 'last_error' => null,
            'next_attempt_at' => null, 'lock_token' => null, 'locked_until' => null, 'updated_at' => now(),
        ]);
    }

    private function owned(OrderDelivery $delivery): Builder
    {
        return OrderDelivery::query()->whereKey($delivery->id)->where('state', 'processing')
            ->where('lock_token', $delivery->lock_token)->where('locked_until', '>', now());
    }
}
