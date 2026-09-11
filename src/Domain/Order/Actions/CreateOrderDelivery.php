<?php

declare(strict_types=1);

namespace Domain\Order\Actions;

use Domain\Order\Models\Order;
use Domain\Order\Models\OrderDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class CreateOrderDelivery
{
    public function initial(int $orderId, string $source = 'checkout'): OrderDelivery
    {
        return $this->create($orderId, null, $source, true);
    }

    public function newVersion(int $orderId, string $operationKey): OrderDelivery
    {
        return $this->create($orderId, strtolower($operationKey), 'admin', false);
    }

    private function create(int $orderId, ?string $operationKey, string $source, bool $initial): OrderDelivery
    {
        return DB::transaction(function () use ($orderId, $operationKey, $source, $initial): OrderDelivery {
            // Единый порядок блокировок с изменениями позиций: сначала заказ.
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            if ($initial) {
                $existing = $order->deliveries()->where('channel', 'ftp')->orderByDesc('version')->first();
                if ($existing) {
                    return $existing;
                }
            } else {
                validator(['operation_key' => $operationKey], ['operation_key' => ['required', 'uuid']])->validate();
                $existing = OrderDelivery::query()->where('operation_key', $operationKey)->first();
                if ($existing) {
                    if ((int) $existing->order_id !== $orderId) {
                        throw ValidationException::withMessages(['operation_key' => 'Ключ операции уже использован.']);
                    }

                    return $existing;
                }
            }

            $order->setRelation('orderItems', $order->orderItems()->orderBy('id')->lockForUpdate()->get());
            $order->setRelation('orderCustomer', $order->orderCustomer()->lockForUpdate()->first());
            $order->setRelation('deliveryType', $order->deliveryType()->sharedLock()->first());
            try {
                $file = app(BuildOrderXml::class)->execute($order);
            } catch (Throwable) {
                throw new RuntimeException('Не удалось сформировать XML доставки. Проверьте данные заказа.');
            }
            $version = (int) $order->deliveries()->where('channel', 'ftp')->max('version') + 1;
            $filename = $version === 1 ? $file->filename : preg_replace('/\.xml$/', '_v'.$version.'.xml', $file->filename);

            try {
                return $order->deliveries()->create([
                    'channel' => 'ftp', 'version' => $version,
                    'operation_key' => $operationKey ?? (string) Str::uuid(), 'source' => $source,
                    'xml' => $file->contents, 'disk' => config('order.ftp_upload.disk'),
                    'ftp_path' => rtrim(config('order.ftp_upload.dir'), '/').'/'.$filename,
                    'state' => 'pending', 'next_attempt_at' => now(),
                ]);
            } catch (QueryException) {
                // SQL исключения содержит XML в bindings; не передаём его в журналы.
                throw new RuntimeException('Не удалось сохранить XML доставки в БД.');
            }
        });
    }

    public function retry(int $orderId, int $deliveryId): bool
    {
        return DB::transaction(function () use ($orderId, $deliveryId): bool {
            $delivery = OrderDelivery::query()->where('order_id', $orderId)->where('channel', 'ftp')
                ->whereKey($deliveryId)->lockForUpdate()->firstOrFail();
            if (in_array($delivery->state, ['pending', 'processing', 'retry'], true)) {
                return false;
            }
            $delivery->update([
                'state' => 'pending', 'cycle_attempts' => 0, 'next_attempt_at' => now(),
                'lock_token' => null, 'locked_until' => null,
            ]);

            return true;
        });
    }
}
