<?php

namespace Domain\Content\Actions\Content;

use Domain\Content\Models\SiteMenu;
use Domain\Content\Models\SiteMenuItem;
use Illuminate\Support\Collection;

/**
 * Строит публичное дерево меню `main` — вынесено из LoadPublicPage, чтобы
 * View Composer (см. AppServiceProvider::boot()) мог отдать $menu в шапку и
 * нижнюю мобильную панель на любой странице, а не только на home/about,
 * чьи контроллеры вызывают LoadPublicPage напрямую.
 *
 * Регистрируется как singleton (AppServiceProvider::register()) и
 * мемоизирует дерево на HTTP-запрос — без этого composer и LoadPublicPage
 * строили бы дерево дважды на одном рендере home/about.
 *
 * Персистентный Cache::rememberForever() (как у FilterOptionsRegistry)
 * сознательно не используется: порядок пунктов меняется стрелками
 * MoonShine через nested set (_lft/_rgt пишутся напрямую, минуя обычные
 * save()-хуки), кеш там протухал бы молча. Меню — самое часто редактируемое
 * место CMS, две лишние SELECT на запрос дешевле риска показать стухший
 * порядок.
 */
final class LoadSiteMenu
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $tree = null;

    /** @return array<int, array<string, mixed>> */
    public function handle(): array
    {
        return $this->tree ??= $this->menuTree();
    }

    /** @return array<int, array<string, mixed>> */
    private function menuTree(): array
    {
        $menu = SiteMenu::query()
            ->active()
            ->where('key', 'main')
            ->with(['items' => fn ($query) => $query->active()->with('section')])
            ->first();

        if ($menu === null) {
            return [];
        }

        $items = $menu->items
            ->filter(fn (SiteMenuItem $item) => $item->site_section_id === null || $item->section?->is_active)
            ->values();
        $children = $items->groupBy(fn (SiteMenuItem $item) => $item->parent_id ?? 0);

        return $this->buildMenuLevel($children, 0, []);
    }

    /**
     * @param  Collection<int|string, Collection<int, SiteMenuItem>>  $children
     * @param  array<int, true>  $visited
     * @return array<int, array<string, mixed>>
     */
    private function buildMenuLevel(Collection $children, int $parentId, array $visited): array
    {
        return ($children->get($parentId) ?? collect())
            ->reject(fn (SiteMenuItem $item) => isset($visited[$item->getKey()]) || $item->resolvedUrl() === null)
            ->map(function (SiteMenuItem $item) use ($children, $visited): array {
                $nextVisited = $visited;
                $nextVisited[$item->getKey()] = true;

                return [
                    'label' => trim((string) $item->label) ?: $item->section?->title,
                    'url' => $item->resolvedUrl(),
                    'target' => $item->open_in_new_tab ? '_blank' : '_self',
                    'rel' => $item->open_in_new_tab ? 'noopener noreferrer' : null,
                    'children' => $this->buildMenuLevel($children, (int) $item->getKey(), $nextVisited),
                ];
            })
            ->filter(fn (array $item) => filled($item['label']))
            ->values()
            ->all();
    }
}
