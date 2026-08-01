<?php

namespace Support\Logging\Events;

final readonly class UntappdBeerSyncFailed implements LoggableEvent
{
    public function __construct(
        public int    $beerId,
        public string $exceptionClass,
        public string $errorMessage,
    )
    {
    }

    public function eventType(): string
    {
        return 'untappd_beer.sync_failed';
    }

    public function level(): string
    {
        return 'error';
    }

    public function message(): string
    {
        return "Синхронизация пива #{$this->beerId} не удалась: {$this->errorMessage}";
    }

    public function context(): array
    {
        return [
            'beer_id' => $this->beerId,
            'exception_class' => $this->exceptionClass,
            'error_message' => $this->errorMessage,
        ];
    }
}
