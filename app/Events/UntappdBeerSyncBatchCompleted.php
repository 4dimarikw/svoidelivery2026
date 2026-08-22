<?php

namespace App\Events;

/**
 * Итог прогона `php artisan untappd:sync-beers` — попадает в event_logs через
 * App\Listeners\PersistEventLog (автодискавери по LoggableEvent, как и
 * UntappdBeerSynced/UntappdBeerSyncFailed, которые уже диспатчатся на каждое
 * пиво). Команда раньше возвращала self::SUCCESS даже при 100% ошибок, и
 * прогон не оставлял в журнале ни одной записи об этом.
 */
final readonly class UntappdBeerSyncBatchCompleted implements LoggableEvent
{
    /** @param  list<array{beer_id: int, error_message: string}>  $errors */
    public function __construct(
        public int $candidates,
        public int $synced,
        public int $failed,
        public array $errors,
    ) {}

    public function eventType(): string
    {
        return 'untappd_beer.sync_batch_completed';
    }

    public function level(): string
    {
        return $this->failed > 0 ? 'warning' : 'info';
    }

    public function message(): string
    {
        return "Синхронизация Untappd: обновлено {$this->synced} из {$this->candidates}, ошибок {$this->failed}.";
    }

    public function context(): array
    {
        return [
            'candidates' => $this->candidates,
            'synced' => $this->synced,
            'failed' => $this->failed,
            'errors' => $this->errors,
        ];
    }
}
