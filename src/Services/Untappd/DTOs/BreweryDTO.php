<?php

declare(strict_types = 1);

namespace Services\Untappd\DTOs;

use Illuminate\Support\Arr;

final class BreweryDTO
{
    public function __construct(
        public int $brewery_id,
        public string $brewery_name,
        public string $brewery_slug,
        public string $brewery_page_url,
        public string $brewery_label,
        public string $brewery_label_hd,
        public string $country_name,
        public bool $brewery_in_production,
        public bool $is_independent,
        public ClaimedStatusDTO $claimed_status,
        public int $beer_count,
        public ContactDTO $contact,
        public string $brewery_type,
        public int $brewery_type_id,
        public BreweryLocationDTO $location,
        public RatingDTO $rating,
        public string $brewery_description,
        public StatsDTO $stats,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            brewery_id: (int)Arr::get($data, 'brewery_id', 0),
            brewery_name: Arr::get($data, 'brewery_name', ''),
            brewery_slug: Arr::get($data, 'brewery_slug', ''),
            brewery_page_url: Arr::get($data, 'brewery_page_url', ''),
            brewery_label: Arr::get($data, 'brewery_label', ''),
            brewery_label_hd: Arr::get($data, 'brewery_label_hd', ''),
            country_name: Arr::get($data, 'country_name', ''),
            brewery_in_production: (bool)Arr::get($data, 'brewery_in_production', true),
            is_independent: (bool)Arr::get($data, 'is_independent', false),
            claimed_status: ClaimedStatusDTO::fromArray(Arr::get($data, 'claimed_status', [])),
            beer_count: (int)Arr::get($data, 'beer_count', 0),
            contact: ContactDTO::fromArray(Arr::get($data, 'contact', [])),
            brewery_type: Arr::get($data, 'brewery_type', ''),
            brewery_type_id: (int)Arr::get($data, 'brewery_type_id', 0),
            location: BreweryLocationDTO::fromArray(Arr::get($data, 'location', [])),
            rating: RatingDTO::fromArray(Arr::get($data, 'rating', [])),
            brewery_description: Arr::get($data, 'brewery_description', ''),
            stats: StatsDTO::fromArray(Arr::get($data, 'stats', [])),
        );
    }

    public function toArray(): array
    {
        return [
            'brewery_id'            => $this->brewery_id,
            'brewery_name'          => $this->brewery_name,
            'brewery_slug'          => $this->brewery_slug,
            'brewery_page_url'      => $this->brewery_page_url,
            'brewery_label'         => $this->brewery_label,
            'brewery_label_hd'      => $this->brewery_label_hd,
            'country_name'          => $this->country_name,
            'brewery_in_production' => $this->brewery_in_production,
            'is_independent'        => $this->is_independent,
            'claimed_status'        => $this->claimed_status?->toArray(),
            'beer_count'            => $this->beer_count,
            'contact'               => $this->contact?->toArray(),
            'brewery_type'          => $this->brewery_type,
            'brewery_type_id'       => $this->brewery_type_id,
            'location'              => $this->location?->toArray(),
            'rating'                => $this->rating?->toArray(),
            'brewery_description'   => $this->brewery_description,
            'stats'                 => $this->stats?->toArray(),
        ];
    }
}
