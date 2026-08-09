<?php

namespace Domain\Content\Actions\Content;

use Domain\Content\Models\SiteSection;
use Illuminate\Support\Collection;
use Infrastructure\Settings\SiteSettings;

final readonly class LoadPublicPage
{
    public function __construct(
        private SiteSettings $settings,
        private LoadSiteMenu $menu,
    ) {}

    /** @return array{sections: Collection<int, SiteSection>, menu: array<int, array<string, mixed>>, settings: SiteSettings} */
    public function handle(string $routeName): array
    {
        $sections = SiteSection::query()
            ->active()
            ->where('route_name', $routeName)
            ->with([
                'publishedBlocks' => fn ($query) => $query->with([
                    'media',
                    'publishedItems' => fn ($items) => $items->with('media'),
                ]),
            ])
            ->ordered()
            ->get();

        return [
            'sections' => $sections,
            // Дерево строит LoadSiteMenu (singleton, мемоизация на запрос) —
            // тот же инстанс, что читает View Composer шапки/нижней панели
            // (см. AppServiceProvider::boot()), так что на home/about оно
            // строится один раз, а не дважды.
            'menu' => $this->menu->handle(),
            'settings' => $this->settings,
        ];
    }
}
