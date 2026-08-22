<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Pages\CatalogImportSettingsPage;
use App\MoonShine\Pages\EventLoggingSettingsPage;
use App\MoonShine\Pages\SiteSettingsPage;
use App\MoonShine\Pages\VkSyncSettingsPage;
use App\MoonShine\Resources\BeerStyle\BeerStyleResource;
use App\MoonShine\Resources\Category\CategoryResource;
use App\MoonShine\Resources\Container\ContainerResource;
use App\MoonShine\Resources\ContentBlock\ContentBlockResource;
use App\MoonShine\Resources\ContentBlockItem\ContentBlockItemResource;
use App\MoonShine\Resources\DeliveryType\DeliveryTypeResource;
use App\MoonShine\Resources\EventLog\EventLogResource;
use App\MoonShine\Resources\Favorite\FavoriteResource;
use App\MoonShine\Resources\Manufacturer\ManufacturerResource;
use App\MoonShine\Resources\MoonshinePermission\MoonshinePermissionResource;
use App\MoonShine\Resources\MoonShineUser\MoonShineUserResource;
use App\MoonShine\Resources\MoonShineUserRole\MoonShineUserRoleResource;
use App\MoonShine\Resources\Order\OrderResource;
use App\MoonShine\Resources\OrderCustomer\OrderCustomerResource;
use App\MoonShine\Resources\OrderItem\OrderItemResource;
use App\MoonShine\Resources\PaymentMethod\PaymentMethodResource;
use App\MoonShine\Resources\Product\ProductResource;
use App\MoonShine\Resources\Property\PropertyResource;
use App\MoonShine\Resources\Seo\SeoResource;
use App\MoonShine\Resources\SiteMenu\SiteMenuResource;
use App\MoonShine\Resources\SiteMenuItem\SiteMenuItemResource;
use App\MoonShine\Resources\SiteSection\SiteSectionResource;
use App\MoonShine\Resources\UntappdBeer\UntappdBeerResource;
use App\MoonShine\Resources\User\UserResource;
use App\MoonShine\Resources\VkPost\VkPostResource;
use App\MoonShine\Resources\Volume\VolumeResource;
use MoonShine\ColorManager\ColorManager;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;
use YuriZoom\MoonShineScheduling\Pages\SchedulingPage;

final class MoonShineLayout extends AppLayout
{
    /**
     * @var null|class-string<PaletteContract>
     */
    protected ?string $palette = PurplePalette::class;

    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        //      return $this->autoloadMenu();

        $isAdmin = request()->user()->isSuperUser();

        return [
            MenuItem::make(OrderResource::class),

            MenuGroup::make(__('moonshine.group.orders'), [
                MenuItem::make(OrderCustomerResource::class),
                MenuItem::make(DeliveryTypeResource::class),
                MenuItem::make(PaymentMethodResource::class),
                MenuItem::make(OrderItemResource::class),
            ], 'shopping-cart')->canSee(fn () => $isAdmin),

            MenuItem::make(UserResource::class),

            MenuGroup::make(__('moonshine.group.users'), [
                MenuItem::make(FavoriteResource::class),
            ], 'users')->canSee(fn () => $isAdmin),

            MenuItem::make(VkPostResource::class),

            MenuGroup::make(__('moonshine.group.catalog'), [
                MenuItem::make(CategoryResource::class),
                MenuItem::make(ProductResource::class),
                MenuItem::make(ManufacturerResource::class),
                MenuItem::make(BeerStyleResource::class),
                MenuItem::make(VolumeResource::class),
                MenuItem::make(ContainerResource::class),
                MenuItem::make(UntappdBeerResource::class),
                MenuItem::make(PropertyResource::class)->canSee(fn () => $isAdmin),
                MenuItem::make(CatalogImportSettingsPage::class)->canSee(fn () => $isAdmin),
                MenuItem::make(SeoResource::class)->canSee(fn () => $isAdmin),

            ], 'squares-2x2'),

            MenuGroup::make('Контент', [
                MenuItem::make(SiteMenuResource::class),
                MenuItem::make(SiteMenuItemResource::class),
                MenuItem::make(SiteSectionResource::class),
                MenuItem::make(ContentBlockResource::class),
                MenuItem::make(ContentBlockItemResource::class),
                MenuItem::make(SiteSettingsPage::class),
                MenuItem::make(VkSyncSettingsPage::class)->canSee(fn () => $isAdmin),

            ], 'document-text'),

            MenuGroup::make('Система', [
                MenuItem::make(MoonShineUserResource::class),
                MenuItem::make(MoonShineUserRoleResource::class),
                MenuItem::make(MoonshinePermissionResource::class),
                MenuItem::make(EventLogResource::class),
                MenuItem::make(EventLoggingSettingsPage::class),
                MenuItem::make(SchedulingPage::class, icon: 'clock'),

            ], 'users')->canSee(fn () => $isAdmin),

        ];
    }

    /**
     * @param  ColorManager  $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }
}
