<?php

declare(strict_types = 1);

namespace Services\Untappd\Repositories;

use Services\Untappd\DTOs\UntappdApiResponse;


interface UntappdInterface
{
    public function get(string $endpoint, array $options = []): ?UntappdApiResponse;
}
