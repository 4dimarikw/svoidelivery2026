<?php

declare(strict_types=1);

namespace Domain\Untappd\Policies;

use App\MoonShine\Traits\MoonshinePermissionPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class UntappdBeerPolicy
{
    use HandlesAuthorization;
    use MoonshinePermissionPolicy;
}
