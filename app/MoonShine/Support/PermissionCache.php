<?php

declare(strict_types=1);

namespace App\MoonShine\Support;

use App\MoonShine\Models\MoonshinePermission;

final class PermissionCache
{
    private array $permissions = [];

    public function get(int $roleId, string $model): ?array
    {
        $key = $roleId.':'.$model;

        if (! array_key_exists($key, $this->permissions)) {
            $this->permissions[$key] = MoonshinePermission::query()
                ->where('moonshine_user_role_id', $roleId)
                ->where('model', $model)
                ->first()?->permissions;
        }

        return $this->permissions[$key];
    }

    public function forget(int $roleId, string $model): void
    {
        unset($this->permissions[$roleId.':'.$model]);
    }
}
