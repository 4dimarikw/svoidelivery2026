<?php

declare(strict_types = 1);

namespace Services\Untappd\Facades;


use Illuminate\Support\Facades\Facade;
use Services\Untappd\DTOs\UntappdApiResponse;
use Services\Untappd\Repositories\UntappdInterface;


/**
 * @method static UntappdApiResponse get(string $endpoint, array $options = [])
 *
 * @see UntappdInterface
 */
final class Untappd extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return UntappdInterface::class;
    }
}
