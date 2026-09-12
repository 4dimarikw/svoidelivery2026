<?php

declare(strict_types=1);

namespace App\MoonShine\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait ChecksSuperUser
{
    /** Скрытые поля также защищены от подмены HTTP-запроса. */
    protected function assertContentStructureUnchanged(Model $model, array $columns): void
    {
        if (! $model->exists || $this->isSuperUser()) {
            return;
        }

        foreach ($columns as $column) {
            $value = request($column);
            if (request()->has($column) && ((! is_scalar($value) && $value !== null)
                || (string) $value !== (string) $model->getAttribute($column))) {
                throw ValidationException::withMessages([
                    $column => 'Изменение служебного поля доступно только суперпользователю.',
                ]);
            }
        }
    }

    /**
     * Служебные поля CMS-ресурсов (ключи, маршруты, структура меню и т.п.)
     * видны только суперюзеру — роль Manager правит только содержимое.
     */
    protected function isSuperUser(): bool
    {
        return (bool) request()->user('moonshine')?->isSuperUser();
    }
}
