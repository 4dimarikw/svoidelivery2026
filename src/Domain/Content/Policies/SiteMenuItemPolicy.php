<?php

declare(strict_types=1);

namespace Domain\Content\Policies;

use App\MoonShine\Traits\MoonshinePermissionPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class SiteMenuItemPolicy
{
    use HandlesAuthorization;
    use MoonshinePermissionPolicy;
}
