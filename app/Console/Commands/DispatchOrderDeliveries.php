<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DeliverOrderXmlJob;
use Domain\Order\Models\OrderDelivery;
use Illuminate\Console\Command;

final class DispatchOrderDeliveries extends Command
{
    protected $signature = 'orders:dispatch-deliveries';

    protected $description = 'Поставить готовые и прерванные доставки XML в очередь';

    public function handle(): int
    {
        if (! config('order.ftp_upload.outbox_enabled', true)) {
            return self::SUCCESS;
        }
        $count = 0;
        OrderDelivery::query()->due()->select('id')->chunkById(100, function ($rows) use (&$count): void {
            foreach ($rows as $row) {
                DeliverOrderXmlJob::dispatch($row->id);
                $count++;
            }
        });
        $this->info("Поставлено доставок: {$count}");

        return self::SUCCESS;
    }
}
