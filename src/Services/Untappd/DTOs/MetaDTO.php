<?php

declare(strict_types = 1);

namespace Services\Untappd\DTOs;


use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;

final class MetaDTO
{
    public function __construct(
        public int $code,
        public ?string $error_detail,
        public ?string $error_type,

    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            code: (int)Arr::get($data, 'code', 0),
            error_detail: Arr::get($data, 'error_detail'),
            error_type: Arr::get($data, 'error_type'),
        );
    }

    public static function fromRequestException(RequestException $e): self
    {
        return new self(
            code: $e->response->status(),
            error_detail: $e->getMessage(),
            error_type: 'http_error',
        );
    }

    public function toArray(): array
    {
        return [
            'code'         => $this->code,
            'error_detail' => $this->error_detail,
            'error_type'   => $this->error_type,
        ];
    }
}
