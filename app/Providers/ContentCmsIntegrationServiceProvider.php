<?php

namespace App\Providers;

use ContentCms\Models\ContentBlock;
use ContentCms\Models\ContentBlockItem;
use ContentCms\Models\SiteMenu;
use ContentCms\Models\SiteMenuItem;
use ContentCms\Models\SiteSection;
use Domain\Content\Policies;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ContentCmsIntegrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(SiteSection::class, Policies\SiteSectionPolicy::class);
        Gate::policy(SiteMenu::class, Policies\SiteMenuPolicy::class);
        Gate::policy(SiteMenuItem::class, Policies\SiteMenuItemPolicy::class);
        Gate::policy(ContentBlock::class, Policies\ContentBlockPolicy::class);
        Gate::policy(ContentBlockItem::class, Policies\ContentBlockItemPolicy::class);

        // Сохраняем discriminator прежних моделей для совместимости media и отката.
        Relation::morphMap([
            'Domain\\Content\\Models\\ContentBlock' => ContentBlock::class,
            'Domain\\Content\\Models\\ContentBlockItem' => ContentBlockItem::class,
        ]);
    }
}
