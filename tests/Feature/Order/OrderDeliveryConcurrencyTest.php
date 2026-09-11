<?php

declare(strict_types=1);

namespace Tests\Feature\Order;

use Domain\Order\Actions\CreateOrderDelivery;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderDelivery;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OrderDeliveryConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('svoidelivery2026_test', DB::selectOne('SELECT DATABASE() AS db')->db);
        Artisan::call('migrate:fresh', ['--force' => true]);
        config(['filesystems.disks.ftp' => ['driver' => 'local', 'root' => storage_path('framework/testing/order-delivery-ftp')]]);
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    private function processes(array $operations, ?Order $lockOrder = null, ?OrderDelivery $lockDelivery = null): array
    {
        $config = config('database.connections.mysql');
        $processes = [];
        DB::beginTransaction();
        try {
            if ($lockOrder) {
                Order::whereKey($lockOrder->id)->lockForUpdate()->firstOrFail();
            }
            if ($lockDelivery) {
                OrderDelivery::whereKey($lockDelivery->id)->lockForUpdate()->firstOrFail();
            }
            foreach ($operations as $operation) {
                $process = new Process([PHP_BINARY, '-d', 'sys_temp_dir='.sys_get_temp_dir(), base_path('tests/Support/order-delivery-worker.php')], base_path(), [
                    'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => base_path('storage/framework/testing/no-delivery-config.php'),
                    'DB_CONNECTION' => 'mysql', 'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'],
                    'DB_DATABASE' => $config['database'], 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'DB_URL' => '',
                    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'null',
                    'MAIL_MAILER' => 'array', 'ORDER_FTP_OUTBOX_ENABLED' => 'true',
                ], json_encode($operation), 20);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $deadline = microtime(true) + 10;
                while (! str_contains($process->getOutput(), 'READY') && $process->isRunning() && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertStringContainsString('READY', $process->getOutput(), $process->getErrorOutput());
            }
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                $results[] = json_decode(trim(str_replace('READY', '', $process->getOutput())), true);
            }

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }

    public function test_same_operation_is_unique_and_independent_operations_get_distinct_versions(): void
    {
        $order = Order::create([]);
        $operation = ['operation' => 'create', 'order_id' => $order->id, 'key' => (string) Str::uuid()];
        $results = $this->processes([$operation, $operation], lockOrder: $order);
        $this->assertSame($results[0], $results[1]);
        $results = $this->processes([
            [...$operation, 'key' => (string) Str::uuid()], [...$operation, 'key' => (string) Str::uuid()],
        ], lockOrder: $order);
        $versions = array_column($results, 'version');
        sort($versions);
        $this->assertSame([2, 3], $versions);
        $this->assertSame(3, $order->deliveries()->count());
    }

    public function test_two_workers_upload_only_once(): void
    {
        $delivery = app(CreateOrderDelivery::class)->initial(Order::create([])->id);
        $operation = ['operation' => 'deliver', 'delivery_id' => $delivery->id];
        $this->processes([$operation, $operation], lockDelivery: $delivery);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->assertSame('sent', $delivery->fresh()->state);
        $this->assertSame($delivery->xml, Storage::disk('ftp')->get($delivery->ftp_path));
    }

    public function test_process_death_before_and_after_upload_recovers_the_same_bytes(): void
    {
        foreach (['before', 'after'] as $phase) {
            $delivery = app(CreateOrderDelivery::class)->initial(Order::create([])->id);
            $operation = ['operation' => 'deliver', 'delivery_id' => $delivery->id];
            $this->processes([[...$operation, 'crash' => $phase]]);
            $this->assertSame('processing', $delivery->fresh()->state);
            $this->assertSame($phase === 'after', Storage::disk('ftp')->exists($delivery->ftp_path));
            DB::table('order_deliveries')->where('id', $delivery->id)->update(['locked_until' => now()->subSecond()]);
            $this->processes([$operation]);
            $this->assertSame('sent', $delivery->fresh()->state);
            $this->assertSame(2, $delivery->fresh()->attempts);
            $this->assertSame($delivery->xml, Storage::disk('ftp')->get($delivery->ftp_path));
        }
    }
}
