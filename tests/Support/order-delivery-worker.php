<?php

declare(strict_types=1);

use Domain\Order\Actions\CreateOrderDelivery;
use Domain\Order\Actions\DeliverOrderXml;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::selectOne('SELECT DATABASE() AS db')->db !== 'svoidelivery2026_test') {
    throw new RuntimeException('Worker requires the isolated test database');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
config(['filesystems.disks.ftp' => ['driver' => 'local', 'root' => storage_path('framework/testing/order-delivery-ftp')]]);
if (isset($input['crash'])) {
    $disk = Storage::disk('ftp');
    $transport = new class($disk, $input['crash'])
    {
        public function __construct(private $disk, private string $phase) {}

        public function getAdapter(): object
        {
            return new stdClass;
        }

        public function put($path, $contents): bool
        {
            if ($this->phase === 'before') {
                exit(0);
            }
            $this->disk->put($path, $contents);
            exit(0);
        }
    };
    Storage::shouldReceive('disk')->with('ftp')->andReturn($transport);
}
echo "READY\n";
flush();
if ($input['operation'] === 'create') {
    $delivery = app(CreateOrderDelivery::class)->newVersion($input['order_id'], $input['key']);
    echo json_encode(['id' => $delivery->id, 'version' => $delivery->version]);
} else {
    app(DeliverOrderXml::class)->execute($input['delivery_id']);
    echo json_encode(['done' => true]);
}
