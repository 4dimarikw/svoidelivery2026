<?php

declare(strict_types=1);

namespace Domain\Profile\Policies;

use App\MoonShine\Traits\MoonshinePermissionPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProfilePolicy
{
    use HandlesAuthorization;
    use MoonshinePermissionPolicy;
}
