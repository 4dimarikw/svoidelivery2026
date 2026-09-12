<?php

namespace Domain\Content\Actions\Content;

use ContentCms\Actions\Menu\BuildMenu;

/** Мемоизирует общее меню в пределах запроса. */
final class LoadSiteMenu
{
    private ?array $tree = null;

    public function __construct(private readonly BuildMenu $menus) {}

    /** @return array<int, array<string, mixed>> */
    public function handle(): array
    {
        return $this->tree ??= $this->menus->handle('main');
    }
}
