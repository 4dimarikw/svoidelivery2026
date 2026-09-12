<?php

namespace Domain\Content\Actions\Content;

use ContentCms\Actions\Content\LoadPublishedSections;
use ContentCms\Models\SiteSection;
use Illuminate\Support\Collection;
use Infrastructure\Settings\SiteSettings;

final readonly class LoadPublicPage
{
    public function __construct(
        private SiteSettings $settings,
        private LoadPublishedSections $sections,
        private LoadSiteMenu $menu,
    ) {}

    /** @return array{sections: Collection<int, SiteSection>, menu: array<int, array<string, mixed>>, settings: SiteSettings} */
    public function handle(string $routeName): array
    {
        return [
            'sections' => $this->sections->handle($routeName),
            // Дерево строит LoadSiteMenu (scoped, мемоизация на запрос) —
            // тот же инстанс, что читает View Composer шапки/нижней панели
            // (см. AppServiceProvider::boot()), так что на home/about оно
            // строится один раз, а не дважды.
            'menu' => $this->menu->handle(),
            'settings' => $this->settings,
        ];
    }
}
