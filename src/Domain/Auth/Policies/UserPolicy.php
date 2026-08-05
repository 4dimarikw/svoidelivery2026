<?php

declare(strict_types=1);

namespace Domain\Auth\Policies;

use App\MoonShine\Traits\MoonshinePermissionPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;
    use MoonshinePermissionPolicy;
}
