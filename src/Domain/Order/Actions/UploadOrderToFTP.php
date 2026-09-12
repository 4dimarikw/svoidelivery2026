<?php

declare(strict_types=1);

namespace Domain\Order\Actions;

use App\Events\Order\OrderFtpUploadFailed;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

/**
 * Возвращаемое true подтверждает сохранение заявки на доставку; результат сетевой отправки хранится отдельно.
 */
class UploadOrderToFTP
{
    public function __invoke(int $orderId): bool
    {
        return $this->execute($orderId);
    }

    public function execute(int $orderId, bool $queueOnFailure = true): bool
    {
        try {
            app(CreateOrderDelivery::class)->initial($orderId, 'legacy');

            return true;
        } catch (Throwable $e) {
            event(new OrderFtpUploadFailed(
                orderId: $orderId, orderNumber: null,
                reason: $e instanceof ModelNotFoundException ? 'order_not_found' : 'upload_failed',
                exceptionClass: $e::class, errorMessage: 'Не удалось сохранить заявку доставки.',
            ));

            return false;
        }
    }
}
