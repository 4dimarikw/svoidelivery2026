<?php

declare(strict_types=1);

namespace App\MoonShine\Support;

use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Core\Exceptions\MoonShineException;
use MoonShine\Support\Enums\ToastType;

/**
 * Blocks deleting a dictionary row that still has dependents through a
 * restrictOnDelete foreign key — without this, the DB rejects the DELETE and
 * the admin sees a raw QueryException / 500 instead of a readable message.
 *
 * The resource declares which relations to check via deletionGuards(); this
 * covers both the single delete and mass-delete paths, since ModelResource's
 * massDelete() routes each row through the same delete() → beforeDeleting().
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
                // toast() must be called before the throw: MoonShineController::
                // reportAndResponse() replaces $e->getMessage() with a generic
                // string in production, but prefers a flashed session('toast').
                toast($message, ToastType::ERROR);

                throw new MoonShineException($message);
            }
        }

        return $item;
    }
}
