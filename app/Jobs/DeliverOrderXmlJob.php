<?php

declare(strict_types=1);

namespace App\Jobs;

use Domain\Order\Actions\DeliverOrderXml;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class DeliverOrderXmlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $deliveryId)
    {
        $this->afterCommit();
    }

    public function handle(DeliverOrderXml $action): void
    {
        $action->execute($this->deliveryId);
    }
}
