<?php

declare(strict_types = 1);

namespace Services\Untappd\DTOs;


use Illuminate\Support\Arr;

final class BeerResponseDTO
{

    public function __construct(
        public ?BeerDTO $beer
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            beer: BeerDTO::fromArray(Arr::get($data, 'beer')),
        );
    }

    public function toArray(): array
    {
        return [
            'beer' => $this->beer?->toArray(),
        ];
    }
}
