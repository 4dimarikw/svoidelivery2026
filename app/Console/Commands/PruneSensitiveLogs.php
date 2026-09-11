<?php

namespace App\Console\Commands;

use Domain\Logging\Models\EventLog;
use Illuminate\Console\Command;
use Illuminate\Queue\Failed\PrunableFailedJobProvider;

class PruneSensitiveLogs extends Command
{
    protected $signature = 'logs:prune-sensitive {--dry-run : Только подсчитать просроченные события; ничего не удалять}';

    protected $description = 'Удалить журналы событий и ошибки очереди старше настроенного срока';

    public function handle(): int
    {
        $cutoff = now()->subDays(max(1, (int) config('log-retention.event_days', 90)));
        $expired = fn () => EventLog::query()->where('created_at', '<', $cutoff);
        if ($this->option('dry-run')) {
            $this->info('Просроченных событий: '.$expired()->count().'. Удаление событий и failed_jobs отключено.');

            return self::SUCCESS;
        }
        $events = 0;
        for ($batch = 0; $batch < 100; $batch++) {
            $ids = $expired()->orderBy('created_at')->orderBy('id')->limit(1000)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            // Повторно проверяем дату: обновлённое между чтением и удалением событие сохраняется.
            $events += $expired()->whereKey($ids)->delete();
        }
        $provider = app('queue.failer');
        $jobs = 0;
        if ($provider instanceof PrunableFailedJobProvider) {
            $jobs = $provider->prune(now()->subDays(max(1, (int) config('log-retention.failed_job_days', 7))));
        } else {
            $this->warn('Драйвер failed_jobs не поддерживает автоматическую очистку.');
        }
        $this->info("Удалено событий: {$events}; ошибок очереди: {$jobs}");

        return self::SUCCESS;
    }
}
