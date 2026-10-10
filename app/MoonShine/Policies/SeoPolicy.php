<?php

declare(strict_types=1);

namespace App\MoonShine\Policies;

use App\MoonShine\Traits\MoonshinePermissionPolicy;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Support\Enums\Ability;

final class SeoPolicy
{
    use MoonshinePermissionPolicy;

    protected function isCan(MoonshineUser $moonshineUser, string $model, Ability $ability): bool
    {
        return $moonshineUser->isSuperUser();
    }
}
