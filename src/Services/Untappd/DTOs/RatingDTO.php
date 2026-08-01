<?php

declare(strict_types = 1);

namespace Services\Untappd\DTOs;


use Illuminate\Support\Arr;

final class RatingDTO
{
    public function __construct(
        public ?int $count,
        public ?float $rating_score,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            count: (int)Arr::get($data, 'count', null),
            rating_score: (float)Arr::get($data, 'rating_score', null),
        );
    }

    public function toArray(): array
    {
        return [
            'count'        => $this->count,
            'rating_score' => $this->rating_score,
        ];
    }
}
