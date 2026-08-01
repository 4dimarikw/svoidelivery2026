<?php

declare(strict_types = 1);

namespace Services\Untappd\DTOs;


use Illuminate\Support\Arr;

final class BreweryResponseDTO
{

    public function __construct(
        public ?BreweryDTO $brewery
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            brewery: BreweryDTO::fromArray(Arr::get($data, 'brewery')),
        );
    }

    public function toArray(): array
    {
        return [
            'beer' => $this->brewery?->toArray(),
        ];
    }
}
