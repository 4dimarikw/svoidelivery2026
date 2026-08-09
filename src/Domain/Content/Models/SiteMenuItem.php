<?php

namespace Domain\Content\Models;

use Domain\Content\Concerns\HasPublicationState;
use Domain\Content\Observers\SiteMenuItemObserver;
use Domain\Content\Support\SafeContentUrl;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Kalnoy\Nestedset\NodeTrait;
use LogicException;

#[ObservedBy(SiteMenuItemObserver::class)]
class SiteMenuItem extends Model
{
    use HasFactory;
    use HasPublicationState;
    use NodeTrait {
        callPendingAction as private callNestedSetPendingAction;
        setParentIdAttribute as private setNestedSetParentIdAttribute;
    }

    protected $attributes = [
        'open_in_new_tab' => false,
        '_lft' => 0,
        '_rgt' => 0,
        'is_active' => true,
    ];

    protected $fillable = [
        'site_menu_id',
        'parent_id',
        'site_section_id',
        'external_url',
        'label',
        'open_in_new_tab',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'open_in_new_tab' => 'boolean',
            '_lft' => 'integer',
            '_rgt' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(SiteMenu::class, 'site_menu_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy($query->qualifyColumn($this->getLftName()))
            ->orderBy($query->qualifyColumn($this->getKeyName()));
    }

    protected function getScopeAttributes(): array
    {
        return ['site_menu_id'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class, 'site_section_id');
    }

    public function resolvedUrl(): ?string
    {
        if ($this->site_section_id !== null) {
            return $this->section?->url();
        }

        return SafeContentUrl::resolve($this->external_url);
    }

    public function setParentIdAttribute(mixed $value): void
    {
        try {
            $this->setNestedSetParentIdAttribute($value);
        } catch (LogicException|ModelNotFoundException $exception) {
            throw ValidationException::withMessages([
                'parent_id' => 'Родитель должен принадлежать тому же меню и не образовывать цикл.',
            ]);
        }
    }

    protected function callPendingAction(): mixed
    {
        SiteMenuItemObserver::validate($this);

        return $this->callNestedSetPendingAction();
    }

    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(fn (): bool => parent::save($options), 3);
    }

    public function delete()
    {
        return $this->getConnection()->transaction(fn (): ?bool => parent::delete(), 3);
    }
}
