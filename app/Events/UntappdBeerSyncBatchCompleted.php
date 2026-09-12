<?php

namespace App\Events;

/**
 * Итог команды untappd:sync-beers записывается в event_logs через PersistEventLog. События UntappdBeerSynced и
 * UntappdBeerSyncFailed отражают результаты отдельных записей.
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
