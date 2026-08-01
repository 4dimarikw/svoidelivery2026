<?php

declare(strict_types = 1);

namespace Services\Untappd\DTOs;


use Illuminate\Support\Arr;

final class TimeMeasurementDTO
{
    public function __construct(
        public ?float $time,
        public ?string $measure
    ) {
    }

    public static function fromArray(?array $data): ?self
    {
        return new self(
            time: Arr::get($data, 'time'),
            measure: Arr::get($data, 'measure'),
        );
    }

    public function toArray(): array
    {
        return [
            'time'    => $this->time,
            'measure' => $this->measure,
        ];
    }
}
