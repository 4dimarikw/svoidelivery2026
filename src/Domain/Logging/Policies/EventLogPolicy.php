<?php

declare(strict_types=1);

namespace Domain\Logging\Policies;

use App\MoonShine\Traits\MoonshinePermissionPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventLogPolicy
{
    use HandlesAuthorization;
    use MoonshinePermissionPolicy;
}
