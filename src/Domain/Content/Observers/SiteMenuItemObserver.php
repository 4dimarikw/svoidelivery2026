<?php

namespace Domain\Content\Observers;

use App\Models\SiteMenuItem;
use Illuminate\Validation\ValidationException;

class SiteMenuItemObserver
{
    public function saving(SiteMenuItem $item): void
    {
        self::validate($item);
    }

    public static function validate(SiteMenuItem $item): void
    {
        $hasSection = $item->site_section_id !== null;
        $hasExternalUrl = trim((string) $item->external_url) !== '';

        if ($hasSection === $hasExternalUrl) {
            throw ValidationException::withMessages([
                'target' => 'Укажите ровно одну цель: раздел сайта или внешний URL.',
            ]);
        }

        if ($hasExternalUrl && $item->resolvedUrl() === null) {
            throw ValidationException::withMessages([
                'external_url' => 'Разрешены http/https URL, локальные пути и якоря.',
            ]);
        }

        if ($item->exists && $item->isDirty('site_menu_id')) {
            throw ValidationException::withMessages([
                'site_menu_id' => 'Для переноса пункта в другое меню используйте действие «Перенести».',
            ]);
        }

        if ($item->parent_id === null) {
            return;
        }

        if ($item->exists && (int) $item->parent_id === (int) $item->getKey()) {
            self::throwInvalidParent();
        }

        $parent = SiteMenuItem::query()->find($item->parent_id);

        if ($parent === null || (int) $parent->site_menu_id !== (int) $item->site_menu_id) {
            self::throwInvalidParent();
        }

        $visited = [];
        while ($parent !== null) {
            if (($item->exists && (int) $parent->getKey() === (int) $item->getKey())
                || isset($visited[$parent->getKey()])) {
                self::throwInvalidParent();
            }

            $visited[$parent->getKey()] = true;
            $parent = $parent->parent;
        }
    }

    private static function throwInvalidParent(): never
    {
        throw ValidationException::withMessages([
            'parent_id' => 'Родитель должен принадлежать тому же меню и не образовывать цикл.',
        ]);
    }
}
