<?php

declare(strict_types = 1);

namespace Services\Untappd\DTOs;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;

final class UntappdApiResponse
{
    public function __construct(
        public ?MetaDTO $meta,
        public ?RateLimitDTO $rate_limit,
        public BeerResponseDTO|BreweryResponseDTO|null $response,
    ) {
    }

    public static function fromResponse(Response $response): self
    {
        $data = $response->json();

        $meta = Arr::get($data, 'meta');
        $rate_limit = Arr::get($data, 'rate_limit');
        $responseData = Arr::get($data, 'response');

        return new self(
            meta: is_null($meta) ? null : MetaDTO::fromArray($meta),
            rate_limit: is_null($rate_limit) ? null : RateLimitDTO::fromArray(Arr::get($data, 'rate_limit')),
            response: is_null($responseData) ? null : self::getResponseData($responseData),
        );
    }

    public static function fromRequestException(RequestException $e): self
    {
        $responseData = Arr::get($e->response->json(), 'response');

        return new self(
            meta: MetaDTO::fromRequestException($e),
            rate_limit: null,
            response: is_null($responseData) ? null : self::getResponseData($responseData),

        );
    }

    public function toArray(): array
    {
        return [
            'meta'       => $this->meta?->toArray(),
            'rate_limit' => $this->rate_limit?->toArray(),
            'response'   => $this->response?->toArray(),
        ];
    }

    private static function getResponseData(array $response): BeerResponseDTO|BreweryResponseDTO|null
    {
        if (isset($response['beer'])) {
            return BeerResponseDTO::fromArray($response);
        }

        if (isset($response['brewery'])) {
            return BreweryResponseDTO::fromArray($response);
        }

        return null;
    }

}
