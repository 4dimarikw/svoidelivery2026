<?php

declare(strict_types=1);

namespace Domain\Catalog\Policies;

use App\MoonShine\Traits\MoonshinePermissionPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;
    use MoonshinePermissionPolicy;
}
