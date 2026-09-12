<?php

declare(strict_types=1);

namespace App\MoonShine\Support;

use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Core\Exceptions\MoonShineException;
use MoonShine\Support\Enums\ToastType;

/**
 * Запрещает удаление справочной записи с зависимостями по внешнему ключу restrictOnDelete и показывает понятное
 * сообщение. deletionGuards() задаёт проверяемые связи. Одиночное и массовое удаление проходят через delete() и
 * beforeDeleting().
 */
trait GuardsRelatedDeletion
{
    /**
     * @return array<string, string> relation method name => error message
     */
    abstract protected function deletionGuards(): array;

    protected function beforeDeleting(DataWrapperContract $item): DataWrapperContract
    {
        $model = $item->getOriginal();

        foreach ($this->deletionGuards() as $relation => $message) {
            if ($model->{$relation}()->exists()) {
                // toast() вызывается до исключения: в production MoonShine заменяет текст исключения общим
                // сообщением, но отдаёт приоритет уведомлению из сессии.
                toast($message, ToastType::ERROR);

                throw new MoonShineException($message);
            }
        }

        return $item;
    }
}
