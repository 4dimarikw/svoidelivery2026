<?php

namespace Domain\Content\Observers;

use Domain\Content\Models\SiteMenuItem;
use Illuminate\Validation\ValidationException;

class SiteMenuItemObserver
{
    public function saving(SiteMenuItem $item): void
    {
        self::validate($item);
    }

    public static function validate(SiteMenuItem $item): void
    {
        $key = trim((string)$item->key);

        if ($key === '' || mb_strlen($key) > 100 || preg_match('/^[\pL\pM\pN_-]+$/u', $key) !== 1) {
            throw ValidationException::withMessages([
                'key' => 'Ключ обязателен, должен быть не длиннее 100 символов и содержать только буквы, цифры, дефис или подчёркивание.',
            ]);
        }

        if ($item->exists && $item->isDirty('key')) {
            throw ValidationException::withMessages([
                'key' => 'Ключ пункта меню нельзя изменить после создания.',
            ]);
        }

        $duplicateKey = SiteMenuItem::query()
            ->where('site_menu_id', $item->site_menu_id)
            ->where('key', $key)
            ->when($item->exists, static fn($query) => $query->whereKeyNot($item->getKey()))
            ->exists();

        if ($duplicateKey) {
            throw ValidationException::withMessages([
                'key' => 'В выбранном меню уже существует пункт с таким ключом.',
            ]);
        }

        $hasSection = $item->site_section_id !== null;
        $hasExternalUrl = trim((string)$item->external_url) !== '';

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

        if ($item->exists && (int)$item->parent_id === (int)$item->getKey()) {
            self::throwInvalidParent();
        }

        $parent = SiteMenuItem::query()->find($item->parent_id);

        if ($parent === null || (int)$parent->site_menu_id !== (int)$item->site_menu_id) {
            self::throwInvalidParent();
        }

        $visited = [];
        while ($parent !== null) {
            if (($item->exists && (int)$parent->getKey() === (int)$item->getKey())
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
