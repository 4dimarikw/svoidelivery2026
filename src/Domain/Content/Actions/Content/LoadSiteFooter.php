<?php

namespace Domain\Content\Actions\Content;

use Domain\Content\Models\ContentBlock;
use Domain\Content\Models\SiteSection;

/**
 * Отдаёт единственный опубликованный блок глобальной секции `footer`
 * (SiteSection.route_name = null, см. миграцию
 * make_site_sections_route_name_nullable) — калька с LoadSiteMenu: вынесено
 * из LoadPublicPage (тот отдаёт секции по конкретному route_name, подвал же
 * должен быть виден на любой странице сразу), singleton
 * (AppServiceProvider::register()), мемоизация на HTTP-запрос, без
 * persistent-кеша по той же причине, что у LoadSiteMenu.
 */
final class LoadSiteFooter
{
    private bool $loaded = false;

    private ?ContentBlock $block = null;

    public function handle(): ?ContentBlock
    {
        if ($this->loaded) {
            return $this->block;
        }

        // Без media/publishedItems в eager-load — footer_info их не использует
        // (2 коротких текстовых поля, ни ссылок, ни картинок, см.
        // Domain\Content\Types\FooterBlockType). Держим запрос минимальным:
        // подвал рендерится на КАЖДОЙ публичной странице, лишние join'ы тут
        // дороже, чем на /about, которая одна.
        $section = SiteSection::query()
            ->active()
            ->where('key', 'footer')
            ->with('publishedBlocks')
            ->first();

        $this->block = $section?->publishedBlocks->first();
        $this->loaded = true;

        return $this->block;
    }
}
