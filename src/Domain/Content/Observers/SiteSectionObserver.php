<?php

namespace Domain\Content\Observers;


use Domain\Content\Models\SiteSection;
use Illuminate\Validation\ValidationException;

class SiteSectionObserver
{
    public function deleting(SiteSection $section): void
    {
        if ($section->menuItems()->exists()) {
            throw ValidationException::withMessages([
                'section' => 'Раздел используется в меню. Сначала удалите ссылки на него.',
            ]);
        }

        $section->blocks()->eachById(static fn($block) => $block->delete());
    }
}
