<?php

declare(strict_types = 1);

namespace Services\Untappd\DTOs;


use Illuminate\Support\Arr;

final class StatsDTO
{
    public function __construct(
        public int $total_count,
        public int $unique_count,
        public int $monthly_count,
        public int $weekly_count,
        public int $user_count,
        public float $age_on_service,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            total_count: (int)Arr::get($data, 'total_count', null),
            unique_count: (int)Arr::get($data, 'unique_count', null),
            monthly_count: (int)Arr::get($data, 'monthly_count', null),
            weekly_count: (int)Arr::get($data, 'weekly_count', null),
            user_count: (int)Arr::get($data, 'user_count', null),
            age_on_service: (float)Arr::get($data, 'age_on_service', null),
        );
    }

    public function toArray(): array
    {
        return [
            'total_count'    => $this->total_count,
            'unique_count'   => $this->unique_count,
            'monthly_count'  => $this->monthly_count,
            'weekly_count'   => $this->weekly_count,
            'user_count'     => $this->user_count,
            'age_on_service' => $this->age_on_service,
        ];
    }
}
