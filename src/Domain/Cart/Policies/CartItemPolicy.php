<?php

declare(strict_types=1);

namespace Domain\Cart\Policies;

use App\MoonShine\Traits\MoonshinePermissionPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class CartItemPolicy
{
    use HandlesAuthorization;
    use MoonshinePermissionPolicy;
}
