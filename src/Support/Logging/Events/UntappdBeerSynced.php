<?php

namespace Support\Logging\Events;

final readonly class UntappdBeerSynced implements LoggableEvent
{
    public function __construct(
        public int     $beerId,
        public string  $name,
        public ?string $brewery,
        public int     $ratingCount,
        public float   $ratingScore,
    )
    {
    }

    public function eventType(): string
    {
        return 'untappd_beer.synced';
    }

    public function level(): string
    {
        return 'info';
    }

    public function message(): string
    {
        return "Синхронизировано пиво #{$this->beerId}: {$this->name}";
    }

    public function context(): array
    {
        return [
            'beer_id' => $this->beerId,
            'name' => $this->name,
            'brewery' => $this->brewery,
            'rating_count' => $this->ratingCount,
            'rating_score' => $this->ratingScore,
        ];
    }
}
