<?php

declare(strict_types = 1);

namespace Services\Untappd\DTOs;

use Illuminate\Support\Arr;

final class RateLimitDTO
{
    public function __construct(
        public ?int $limit,
        public ?int $remaining
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            limit: (int)Arr::get($data, 'limit'),
            remaining: Arr::get($data, 'remaining'),
        );
    }

    public function toArray(): array
    {
        return [
            'limit'     => $this->limit,
            'remaining' => $this->remaining,
        ];
    }
}
