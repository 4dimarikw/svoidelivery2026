<?php

declare(strict_types = 1);

namespace Services\Untappd\DTOs;


final class ContactDTO
{
    public function __construct(
        public string $twitter,
        public string $facebook,
        public string $instagram,
        public string $url
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            twitter: $data['twitter'] ?? '',
            facebook: $data['facebook'] ?? '',
            instagram: $data['instagram'] ?? '',
            url: $data['url'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'twitter'   => $this->twitter,
            'facebook'  => $this->facebook,
            'instagram' => $this->instagram,
            'url'       => $this->url,
        ];
    }
}
