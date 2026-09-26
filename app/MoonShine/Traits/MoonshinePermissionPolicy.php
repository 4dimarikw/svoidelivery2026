<?php

namespace App\MoonShine\Traits;

use App\MoonShine\Support\PermissionCache;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Support\Enums\Ability;

trait MoonshinePermissionPolicy
{
    use HandlesAuthorization;

    public function viewAny(MoonshineUser $moonshineUser, Model $model): bool
    {
        return $this->isCan($moonshineUser, $model::class, Ability::VIEW_ANY);
    }

    public function view(MoonshineUser $moonshineUser, Model $model): bool
    {
        return $this->isCan($moonshineUser, $model::class, Ability::VIEW);
    }

    public function create(MoonshineUser $moonshineUser, Model $model): bool
    {
        return $this->isCan($moonshineUser, $model::class, Ability::CREATE);
    }

    public function update(MoonshineUser $moonshineUser, Model $model): bool
    {
        return $this->isCan($moonshineUser, $model::class, Ability::UPDATE);
    }

    public function delete(MoonshineUser $moonshineUser, Model $model): bool
    {
        return $this->isCan($moonshineUser, $model::class, Ability::DELETE);
    }

    public function restore(MoonshineUser $moonshineUser, Model $model): bool
    {
        return $this->isCan($moonshineUser, $model::class, Ability::RESTORE);
    }

    public function forceDelete(MoonshineUser $moonshineUser, Model $model): bool
    {
        return $this->isCan($moonshineUser, $model::class, Ability::FORCE_DELETE);
    }

    public function massDelete(MoonshineUser $moonshineUser, Model $model): bool
    {
        return $this->isCan($moonshineUser, $model::class, Ability::MASS_DELETE);
    }

    protected function isCan(MoonshineUser $moonshineUser, string $model, Ability $ability): bool
    {
        if ($moonshineUser->isSuperUser()) {
            return true;
        }

        $roleId = $moonshineUser->moonshineUserRole?->id;

        if ($roleId === null) {
            return false;
        }

        $permissions = app(PermissionCache::class)->get($roleId, $model);

        return (bool) ($permissions[$ability->value] ?? false);
    }
}
