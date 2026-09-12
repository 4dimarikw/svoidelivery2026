<?php

namespace Domain\Content\Actions\Content;

use Domain\Content\Models\SiteMenu;
use Domain\Content\Models\SiteMenuItem;
use Illuminate\Support\Collection;

/**
 * Строит публичное дерево меню main для View Composer и LoadPublicPage. Singleton сохраняет результат на
 * HTTP-запрос. Постоянный кеш не используется: перестановка пунктов через nested set меняет _lft/_rgt напрямую,
 * минуя события save().
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
