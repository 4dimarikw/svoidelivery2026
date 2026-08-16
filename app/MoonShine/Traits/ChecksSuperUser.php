<?php

declare(strict_types=1);

namespace App\MoonShine\Traits;

trait ChecksSuperUser
{
    /**
     * Служебные поля CMS-ресурсов (ключи, маршруты, структура меню и т.п.)
     * видны только суперюзеру — роль Manager правит только содержимое.
     */
    protected function isSuperUser(): bool
    {
        return (bool) request()->user('moonshine')?->isSuperUser();
    }
}
