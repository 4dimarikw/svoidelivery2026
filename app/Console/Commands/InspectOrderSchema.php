<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

final class InspectOrderSchema extends Command
{
    protected $signature = 'orders:inspect-schema';

    protected $description = 'Отчёт O04: версия, миграции, размеры, DDL и сверка сумм (только чтение)';

    public function handle(): int
    {
        $applied = DB::table('migrations')->orderBy('id')->pluck('migration')->all();
        $files = array_map(fn ($path) => pathinfo($path, PATHINFO_FILENAME), File::glob(database_path('migrations/*.php')));
        $tables = ['orders', 'order_items', 'products', 'event_logs'];
        $ddl = [];
        foreach ($tables as $table) {
            $ddl[$table] = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
        }
        $report = [
            'captured_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'server' => DB::selectOne('SELECT VERSION() AS version'),
            'applied_migrations' => $applied,
            'pending_migrations' => array_values(array_diff($files, $applied)),
            'table_estimates' => DB::select("SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('orders','order_items','products','event_logs')"),
            'orders' => DB::table('orders')->selectRaw('COUNT(*) AS row_count, COALESCE(SUM(amount), 0) AS amount')->first(),
            'items' => DB::table('order_items')->selectRaw('COUNT(*) AS row_count, COALESCE(SUM(price * quantity), 0) AS amount')->first(),
            'invalid_items' => DB::table('order_items')->whereRaw('quantity < 1 OR price < 0.01 OR price > 9999999999.99')->count(),
            'ddl' => $ddl,
        ];
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
