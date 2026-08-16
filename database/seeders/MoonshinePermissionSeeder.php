<?php

namespace Database\Seeders;

use App\MoonShine\Models\MoonshinePermission;
use Domain\Auth\Models\User;
use Domain\Catalog\Models\BeerStyle;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\Volume;
use Domain\Content\Models\ContentBlock;
use Domain\Content\Models\ContentBlockItem;
use Domain\Content\Models\SiteMenu;
use Domain\Content\Models\SiteMenuItem;
use Domain\Content\Models\SiteSection;
use Domain\Favorite\Models\Favorite;
use Domain\Order\Models\Order;
use Domain\Order\Models\OrderCustomer;
use Domain\Order\Models\OrderItem;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Database\Seeder;
use MoonShine\Laravel\Models\MoonshineUserRole;

class MoonshinePermissionSeeder extends Seeder
{
    /**
     * Матрица прав роли "Manager" в MoonShine. Ключ — FQCN модели, значение —
     * карта способностей `MoonShine\Support\Enums\Ability`. Все 8 ключей
     * указаны явно (даже `false`) — так строка в БД однозначно соответствует
     * тому, что видно здесь, без домысливания "отсутствующий ключ = false"
     * (хотя `MoonshinePermissionPolicy::isCan()` и так его трактует через
     * `?? false`).
     *
     * Контентные модели (SiteSection/SiteMenu/SiteMenuItem/ContentBlock/
     * ContentBlockItem) — `update => true`: Manager правит содержимое сайта,
     * но не создаёт и не удаляет разделы/меню/блоки (их структура и служебные
     * поля скрыты от Manager на уровне форм — см. соответствующие FormPage/
     * IndexPage в app/MoonShine/Resources).
     */
    private const array PERMISSIONS = [
        Favorite::class => [
            'view' => true, 'create' => false, 'delete' => false, 'update' => false,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
        User::class => [
            'view' => true, 'create' => false, 'delete' => false, 'update' => true,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
        Order::class => [
            'view' => true, 'create' => true, 'delete' => true, 'update' => true,
            'restore' => true, 'viewAny' => true, 'massDelete' => true, 'forceDelete' => true,
        ],
        OrderCustomer::class => [
            'view' => true, 'create' => false, 'delete' => false, 'update' => false,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
        OrderItem::class => [
            'view' => false, 'create' => false, 'delete' => false, 'update' => true,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
        Product::class => [
            'view' => true, 'create' => true, 'delete' => true, 'update' => true,
            'restore' => true, 'viewAny' => true, 'massDelete' => true, 'forceDelete' => true,
        ],
        Manufacturer::class => [
            'view' => true, 'create' => true, 'delete' => true, 'update' => true,
            'restore' => true, 'viewAny' => true, 'massDelete' => true, 'forceDelete' => true,
        ],
        BeerStyle::class => [
            'view' => true, 'create' => false, 'delete' => false, 'update' => true,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
        Volume::class => [
            'view' => true, 'create' => false, 'delete' => false, 'update' => false,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
        Container::class => [
            'view' => true, 'create' => false, 'delete' => false, 'update' => false,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
        UntappdBeer::class => [
            'view' => true, 'create' => false, 'delete' => false, 'update' => false,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
        Category::class => [
            'view' => true, 'create' => false, 'delete' => false, 'update' => true,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
        SiteMenu::class => [
            'view' => true, 'create' => false, 'delete' => false, 'update' => true,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
        SiteMenuItem::class => [
            'view' => true, 'create' => false, 'delete' => false, 'update' => true,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
        SiteSection::class => [
            'view' => true, 'create' => false, 'delete' => false, 'update' => true,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
        ContentBlock::class => [
            'view' => true, 'create' => false, 'delete' => false, 'update' => true,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
        ContentBlockItem::class => [
            'view' => true, 'create' => false, 'delete' => false, 'update' => true,
            'restore' => false, 'viewAny' => true, 'massDelete' => false, 'forceDelete' => false,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roleId = MoonshineUserRole::query()->firstOrCreate(['name' => 'Manager'])->getKey();

        // updateOrCreate по паре (роль, модель) — в таблице нет unique-индекса
        // на эту пару, так что при ручном создании дубля через UI сидер не
        // подчистит хвост, а лишь обновит первую найденную строку.
        foreach (self::PERMISSIONS as $model => $abilities) {
            MoonshinePermission::query()->updateOrCreate(
                ['moonshine_user_role_id' => $roleId, 'model' => $model],
                ['permissions' => $abilities],
            );
        }
    }
}
