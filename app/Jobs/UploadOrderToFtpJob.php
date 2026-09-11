<?php

declare(strict_types=1);

namespace App\Jobs;

use Domain\Order\Actions\UploadOrderToFTP;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Старые сериализованные jobs содержат orderId. Сеть обслуживает новый worker outbox. */
class UploadOrderToFtpJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public array $backoff = [];

    public int $timeout = 120;

    public function __construct(public int $orderId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(): void
    {
        app(UploadOrderToFTP::class)->execute($this->orderId, false);
    }
}
