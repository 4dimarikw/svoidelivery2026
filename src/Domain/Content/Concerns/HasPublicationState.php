<?php

namespace Domain\Content\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasPublicationState
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_active'), true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy($query->qualifyColumn('sort_order'))
            ->orderBy($query->qualifyColumn('id'));
    }
}
