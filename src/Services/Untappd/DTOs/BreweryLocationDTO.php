<?php

declare(strict_types = 1);

namespace Services\Untappd\DTOs;


use Illuminate\Support\Arr;

final class BreweryLocationDTO
{
    public function __construct(
        public string $brewery_address,
        public string $brewery_city,
        public string $brewery_state,
        public ?float $brewery_lat,
        public ?float $brewery_lng,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            brewery_address: Arr::get($data, 'brewery_address', ''),
            brewery_city: $data['brewery_city'] ?? '',
            brewery_state: $data['brewery_state'] ?? '',
            brewery_lat: (float)Arr::get($data, 'lat', null) ?? (float)Arr::get($data, 'brewery_lat', null),
            brewery_lng: (float)Arr::get($data, 'lng', null) ?? (float)Arr::get($data, 'brewery_lng', null),
        );
    }

    public function toArray(): array
    {
        return [
            'brewery_address' => $this->brewery_address,
            'brewery_city'    => $this->brewery_city,
            'brewery_state'   => $this->brewery_state,
            'brewery_lat'     => $this->brewery_lat,
            'brewery_lng'     => $this->brewery_lng,
        ];
    }
}
