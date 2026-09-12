<?php

namespace Domain\Content\Actions\Content;

use ContentCms\Actions\Content\LoadPublishedSectionByKey;
use ContentCms\Models\ContentBlock;

/** Мемоизирует опубликованный блок общего подвала в пределах запроса. */
final class LoadSiteFooter
{
    private bool $loaded = false;

    private ?ContentBlock $block = null;

    public function __construct(private readonly LoadPublishedSectionByKey $sections) {}

    public function handle(): ?ContentBlock
    {
        if (! $this->loaded) {
            $this->block = $this->sections->handle('footer')?->publishedBlocks->first();
            $this->loaded = true;
        }

        return $this->block;
    }
}
