<?php

declare(strict_types=1);

namespace Domain\Favorite\Policies;

use App\MoonShine\Traits\MoonshinePermissionPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class FavoritePolicy
{
    use HandlesAuthorization;
    use MoonshinePermissionPolicy;
}
