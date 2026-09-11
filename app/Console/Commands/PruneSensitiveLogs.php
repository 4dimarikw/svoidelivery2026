<?php

namespace App\Console\Commands;

use Domain\Logging\Models\EventLog;
use Illuminate\Console\Command;
use Illuminate\Queue\Failed\PrunableFailedJobProvider;

class PruneSensitiveLogs extends Command
{
    protected $signature = 'logs:prune-sensitive';

    protected $description = 'Удалить журналы событий и ошибки очереди старше настроенного срока';

    public function handle(): int
    {
        $events = EventLog::query()->where('created_at', '<', now()->subDays(max(1, (int) config('log-retention.event_days', 90))))->delete();
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
