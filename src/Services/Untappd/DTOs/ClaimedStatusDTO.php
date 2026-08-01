<?php

declare(strict_types = 1);

namespace Services\Untappd\DTOs;

use Illuminate\Support\Arr;

final class ClaimedStatusDTO
{
    public function __construct(
        public bool $is_claimed,
        public string $claimed_slug,
        public bool $follow_status,
        public int $follower_count,
        public int $uid,
        public string $mute_status,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            is_claimed: (bool)Arr::get($data, 'is_claimed', false),
            claimed_slug: Arr::get($data, 'claimed_slug', ''),
            follow_status: (bool)Arr::get($data, 'follow_status', false),
            follower_count: (int)Arr::get($data, 'follower_count', 0),
            uid: (int)Arr::get($data, 'uid', 0),
            mute_status: Arr::get($data, 'mute_status', ''),
        );
    }

    public function toArray(): array
    {
        return [
            'is_claimed' => $this->is_claimed,
            'claimed_slug' => $this->claimed_slug,
            'follow_status' => $this->follow_status,
            'follower_count' => $this->follower_count,
            'uid' => $this->uid,
            'mute_status' => $this->mute_status,
        ];
    }
}
