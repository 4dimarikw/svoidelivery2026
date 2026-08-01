<?php

namespace Services\CatalogImport\Contracts;

use Closure;
use Services\CatalogImport\Dto\ImportContext;

interface ImportStage
{
    public function __invoke(ImportContext $ctx, Closure $next): ImportContext;
}
