<?php

declare(strict_types=1);

namespace Infrastructure\Jobs;

use Domain\Cart\Actions\UpdateCartPrices;
use Domain\Cart\Models\Cart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class UpdateCartPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 60;

    public function __construct(public int $afterId = 0) {}

    public function handle(UpdateCartPrices $action): void
    {
        $ids = Cart::query()->where('id', '>', $this->afterId)->whereHas('cartItems')
            ->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            $action->handle($id);
        }
        if ($ids->isNotEmpty() && Cart::query()->where('id', '>', $ids->last())->whereHas('cartItems')->exists()) {
            self::dispatch($ids->last())->onConnection($this->connection)->onQueue($this->queue);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Cart price refresh failed', ['after_id' => $this->afterId, 'exception' => $exception]);
    }
}
