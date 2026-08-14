<?php

declare(strict_types=1);

namespace Domain\Vk\Policies;

use App\MoonShine\Traits\MoonshinePermissionPolicy;
use Illuminate\Auth\Access\HandlesAuthorization;

class VkPostPolicy
{
    use HandlesAuthorization;
    use MoonshinePermissionPolicy;
}
