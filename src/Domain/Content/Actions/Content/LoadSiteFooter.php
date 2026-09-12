<?php

namespace Domain\Content\Actions\Content;

use Domain\Content\Models\ContentBlock;
use Domain\Content\Models\SiteSection;

/**
 * Загружает опубликованный блок глобальной секции footer с route_name=null для публичных страниц. Singleton
 * сохраняет результат в пределах HTTP-запроса; постоянный кеш не используется.
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
