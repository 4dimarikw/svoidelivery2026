<?php

declare(strict_types=1);

namespace App\MoonShine\Handlers;

use Illuminate\Support\Collection;
use MoonShine\Contracts\Core\ResourceContract;
use MoonShine\ImportExport\ImportHandler;

final class PermissionImportHandler extends ImportHandler
{
    public static function process(
        string $path,
        ResourceContract $resource,
        bool $deleteAfter = false,
        string $delimiter = ',',
        array $notifyUsers = [],
    ): Collection {
        try {
            return parent::process($path, $resource, false, $delimiter, $notifyUsers);
        } finally {
            if ($deleteAfter && is_file($path)) {
                unlink($path);
            }
        }
    }
}
