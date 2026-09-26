<?php

declare(strict_types=1);

namespace App\MoonShine\Support;

use App\MoonShine\Pages;
use App\MoonShine\Resources;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Resources\ModelResource;
use YuriZoom\MoonShineScheduling\Pages\SchedulingPage;

final class PermissionModels
{
    // Includes restrictions inherited from the Orders, Users and System menu groups.
    private const array SUPERUSER_MENU = [
        Resources\OrderCustomer\OrderCustomerResource::class,
        Resources\DeliveryType\DeliveryTypeResource::class,
        Resources\PaymentMethod\PaymentMethodResource::class,
        Resources\OrderItem\OrderItemResource::class,
        Resources\Favorite\FavoriteResource::class,
        Resources\Property\PropertyResource::class,
        Resources\Seo\SeoResource::class,
        Resources\MoonShineUser\MoonShineUserResource::class,
        Resources\MoonShineUserRole\MoonShineUserRoleResource::class,
        Resources\MoonshinePermission\MoonshinePermissionResource::class,
        Resources\EventLog\EventLogResource::class,
        Pages\CatalogImportSettingsPage::class,
        Pages\VkSyncSettingsPage::class,
        Pages\EventLoggingSettingsPage::class,
        SchedulingPage::class,
    ];

    public function menuVisible(string $resource, ?MoonshineUser $user): bool
    {
        return ! in_array($resource, self::SUPERUSER_MENU, true) || ($user?->isSuperUser() ?? false);
    }

    public function options(): array
    {
        $options = [];

        foreach (moonshine()->getResources() as $resource) {
            if (! $resource instanceof ModelResource) {
                continue;
            }

            $notes = [];
            if (in_array($resource::class, self::SUPERUSER_MENU, true)) {
                $notes[] = 'меню только для SuperUser';
            }
            if (! $resource->isWithPolicy()) {
                $notes[] = 'матрица не управляет доступом: без policy';
            }

            $options[$resource->getModel()::class] = class_basename($resource->getModel()).' — '.$resource->getTitle()
                .($notes === [] ? '' : ' ('.implode('; ', $notes).')');
        }

        asort($options);

        return $options;
    }
}
