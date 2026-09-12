<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkPost\Pages;

use App\MoonShine\Resources\VkPost\VkPostResource;
use Domain\Vk\Enums\VkImageSendMode;
use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use Infrastructure\Settings\VKSyncSettings;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Components\Thumbnails;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends DetailPage<VkPostResource>
 */
class VkPostDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Ссылка на VK', formatted: fn (VkPost $item) => $item->vkUrl),
            Date::make('Опубликован', 'posted_at')->withTime(),
            Textarea::make('Исходный текст (VK)', 'text'),
            Textarea::make('Текст для рассылки', 'message_text'),
            Json::make('Исходные ссылки VK', 'images'),
            Text::make('Способ отправки картинок', formatted: fn () => VkImageSendMode::from(app(VKSyncSettings::class)->image_send_mode)->label()),
            Text::make('Загружено на сайт', formatted: fn (VkPost $item) => $item->imageDownloadStatus()),
            Text::make('Локальные копии', formatted: fn (VkPost $item) => Thumbnails::make($item->localImageUrls())),
            Json::make('Отклонённые картинки', 'rejected_images'),
            Enum::make('Статус', 'status')->attach(VkPostStatus::class),
            Date::make('Разослан', 'broadcast_at')->withTime(),
            Text::make('Доставки', formatted: fn (VkPost $item) => $item->deliveries->isEmpty()
                ? 'Ещё не было'
                : sprintf(
                    'Всего: %d, отправлено: %d, ошибок: %d',
                    $item->deliveries->count(),
                    $item->deliveries->where('status', 'sent')->count(),
                    $item->deliveries->where('status', 'failed')->count(),
                )),
            Json::make('Сырые данные VK', 'raw'),
        ];
    }
}
