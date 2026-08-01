<?php

declare(strict_types=1);

namespace Services\Untappd\DTOs;


use Illuminate\Support\Arr;

final class BeerDTO
{
    public function __construct(
        public int     $bid,
        public string  $beer_name,
        public string  $beer_label,
        public string  $beer_label_hd,
        public ?string $beer_image,
        public float   $beer_abv,
        public int     $beer_ibu,
        public string  $beer_description,
        public string  $beer_style,
        public ?bool   $is_in_production,
        public string  $beer_slug,
        public ?bool   $is_homebrew,
        public string  $created_at,
        public int     $rating_count,
        public float   $rating_score,
        public string  $brewery,
        public ?int    $auth_rating,
        public ?bool   $wish_list,
        public ?float  $weighted_rating_score,
        public bool    $beer_active
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            bid: (int)Arr::get($data, 'bid', 0),
            beer_name: Arr::get($data, 'beer_name', ''),
            beer_label: Arr::get($data, 'beer_label', ''),
            beer_label_hd: Arr::get($data, 'beer_label_hd', ''),
            beer_image: Arr::get($data, 'beer_image', ''),
            beer_abv: (float)Arr::get($data, 'beer_abv', 0),
            beer_ibu: (int)Arr::get($data, 'beer_ibu', 0),
            beer_description: Arr::get($data, 'beer_description', ''),
            beer_style: Arr::get($data, 'beer_style', ''),
            is_in_production: Arr::get($data, 'is_in_production'),
            beer_slug: Arr::get($data, 'beer_slug', ''),
            is_homebrew: Arr::get($data, 'is_homebrew'),
            created_at: Arr::get($data, 'created_at', '') ?? '',
            rating_count: (int)Arr::get($data, 'rating_count', 0),
            rating_score: (float)Arr::get($data, 'rating_score', 0),
            brewery: self::getBrewery($data),
            auth_rating: Arr::get($data, 'auth_rating'),
            wish_list: Arr::get($data, 'wish_list'),
            weighted_rating_score: Arr::get($data, 'weighted_rating_score'),
            beer_active: (bool)Arr::get($data, 'beer_active', false)
        );
    }

    public function toArray(): array
    {
        return [
            'bid' => $this->bid,
            'beer_name' => $this->beer_name,
            'beer_label' => $this->beer_label,
            'beer_label_hd' => $this->beer_label_hd,
            'beer_image' => $this->beer_image,
            'beer_abv' => $this->beer_abv,
            'beer_ibu' => $this->beer_ibu,
            'beer_description' => $this->beer_description,
            'beer_style' => $this->beer_style,
            'is_in_production' => $this->is_in_production,
            'beer_slug' => $this->beer_slug,
            'is_homebrew' => $this->is_homebrew,
            'created_at' => $this->created_at,
            'rating_count' => $this->rating_count,
            'rating_score' => $this->rating_score,
            'brewery' => $this->brewery,
            'auth_rating' => $this->auth_rating,
            'wish_list' => $this->wish_list,
            'weighted_rating_score' => $this->weighted_rating_score,
            'beer_active' => $this->beer_active,
        ];
    }

    private static function getBrewery(mixed $data)
    {
        $breweryData = Arr::get($data, 'brewery');

        if (is_array($breweryData)) {
            $brewery = BreweryDTO::fromArray(Arr::get($breweryData, 'brewery', []));
            return $brewery->brewery_name;
        }

        return $breweryData;
    }
}
