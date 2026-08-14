<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkPost;

use App\MoonShine\Resources\VkPost\Pages\VkPostDetailPage;
use App\MoonShine\Resources\VkPost\Pages\VkPostFormPage;
use App\MoonShine\Resources\VkPost\Pages\VkPostIndexPage;
use Domain\Vk\Models\VkPost;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\ListOf;

/**
 * Посты приходят только из vk:sync-posts — создание из админки отключено.
 *
 * @extends ModelResource<VkPost, VkPostIndexPage, VkPostFormPage, VkPostDetailPage>
 */
#[Icon('newspaper')]
#[Group('Контент', 'document-text')]
#[Order(65)]
class VkPostResource extends ModelResource
{
    protected string $model = VkPost::class;

    protected bool $withPolicy = true;

    protected string $column = 'vk_post_id';

    protected bool $detailInModal = true;

    protected array $with = ['deliveries'];

    public function getTitle(): string
    {
        return 'Посты VK';
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::CREATE);
    }

    protected function pages(): array
    {
        return [
            VkPostIndexPage::class,
            VkPostFormPage::class,
            VkPostDetailPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'vk_post_id', 'text'];
    }
}
