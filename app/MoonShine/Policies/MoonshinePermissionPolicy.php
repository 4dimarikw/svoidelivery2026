<?php

declare(strict_types=1);

namespace App\MoonShine\Policies;

use App\MoonShine\Traits\MoonshinePermissionPolicy as MoonshinePermissionPolicyTrait;
use Illuminate\Auth\Access\HandlesAuthorization;

class MoonshinePermissionPolicy
{
    use HandlesAuthorization;
    use MoonshinePermissionPolicyTrait;
}
