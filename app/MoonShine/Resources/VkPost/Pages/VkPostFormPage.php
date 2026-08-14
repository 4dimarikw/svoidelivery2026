<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkPost\Pages;

use App\MoonShine\Resources\VkPost\VkPostResource;
use Domain\Vk\Actions\SendVkPostToChatAction;
use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends FormPage<VkPostResource, VkPost>
 */
final class VkPostFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Ссылка на VK', formatted: fn (VkPost $item) => $item->vkUrl)->locked(),
                Date::make('Опубликован', 'posted_at')->withTime()->locked(),
                Textarea::make('Исходный текст (VK)', 'text')->readonly(),
                Textarea::make('Текст для рассылки', 'message_text')
                    ->hint('Пусто — рассылается исходный текст. Telegram понимает только узкое подмножество HTML: <b>, <i>, <a href>, <code>, <pre> — остальные теги приведут к ошибке отправки.'),
                Json::make('Картинки', 'images')->onlyValue('URL')->removable(),
                Enum::make('Статус', 'status')->attach(VkPostStatus::class),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'message_text' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*.value' => ['nullable', 'string', 'max:2048'],
            'status' => ['required', 'string'],
        ];
    }

    protected function buttons(): ListOf
    {
        return parent::buttons()->add(...[
            ActionButton::make('Отправить себе')->method('sendToSelf'),
            ActionButton::make('Разослать всем')->warning()->method('broadcastAll'),
        ]);
    }

    #[AsyncMethod]
    public function sendToSelf(CrudRequestContract $request, SendVkPostToChatAction $send): JsonResponse
    {
        $post = $request->getResource()->getItem();

        if (! $post instanceof VkPost) {
            return JsonResponse::make()->toast('Пост не найден.');
        }

        return VkPostIndexPage::sendTestMessage($post, $send);
    }

    #[AsyncMethod]
    public function broadcastAll(CrudRequestContract $request): JsonResponse
    {
        $post = $request->getResource()->getItem();

        if (! $post instanceof VkPost) {
            return JsonResponse::make()->toast('Пост не найден.');
        }

        return VkPostIndexPage::dispatchBroadcast($post);
    }
}
