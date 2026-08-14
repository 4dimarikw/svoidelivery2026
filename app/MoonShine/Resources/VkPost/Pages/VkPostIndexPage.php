<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkPost\Pages;

use App\MoonShine\Resources\VkPost\VkPostResource;
use Domain\Auth\Models\User;
use Domain\Telegram\Models\TelegramBot;
use Domain\Vk\Actions\SendVkPostToChatAction;
use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Infrastructure\Jobs\BroadcastVkPostJob;
use Infrastructure\Settings\SiteSettings;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\HttpMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Thumbnails;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends IndexPage<VkPostResource>
 */
final class VkPostIndexPage extends IndexPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Date::make('Опубликован', 'posted_at')->format('d.m.Y H:i')->sortable(),
            Text::make('Текст', formatted: fn (VkPost $item) => Str::limit((string) $item->broadcastText, 80)),
            Image::make('Картинки', 'images')->changePreview(fn ($value) => Thumbnails::make($value)),
            Enum::make('Статус', 'status')->attach(VkPostStatus::class),
            Date::make('Разослан', 'broadcast_at')->format('d.m.Y H:i')->sortable(),
            Number::make('Доставок', formatted: fn (VkPost $item) => $item->deliveries->count()),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            Enum::make('Статус', 'status')->attach(VkPostStatus::class),
            Text::make('Тип поста', 'post_type'),
        ];
    }

    /**
     * @return list<QueryTag>
     */
    protected function queryTags(): array
    {
        return [
            QueryTag::make('Черновики', fn (Builder $query) => $query->where('status', VkPostStatus::DRAFT)),
            QueryTag::make('Готовые', fn (Builder $query) => $query->where('status', VkPostStatus::READY)),
            QueryTag::make('Отправленные', fn (Builder $query) => $query->where('status', VkPostStatus::SENT)),
        ];
    }

    protected function buttons(): ListOf
    {
        $events = [AlpineJs::event(JsEvent::TABLE_UPDATED, $this->getListComponentName())];

        return parent::buttons()->add(...[
            ActionButton::make('Отправить себе')
                ->icon('paper-airplane')
                ->showInLine()
                ->method('sendToSelf', events: $events)
                ->async(HttpMethod::POST, events: $events)
                ->withConfirm(
                    title: 'Отправить себе',
                    content: 'Тестовое сообщение уйдёт пользователю из настройки «Email тестового пользователя».',
                    button: 'Отправить',
                ),
            ActionButton::make('Разослать всем')
                ->icon('users')
                ->showInLine()
                ->warning()
                ->method('broadcastAll', events: $events)
                ->async(HttpMethod::POST, events: $events)
                ->withConfirm(
                    title: 'Разослать всем',
                    content: 'Сообщение уйдёт всем пользователям с привязанным Telegram. Отменить рассылку нельзя.',
                    button: 'Разослать',
                ),
        ]);
    }

    #[AsyncMethod]
    public function sendToSelf(CrudRequestContract $request, SendVkPostToChatAction $send): JsonResponse
    {
        $post = $request->getResource()->getItem();

        if (! $post instanceof VkPost) {
            return JsonResponse::make()->toast('Пост не найден.', ToastType::ERROR);
        }

        return $this->sendTestMessage($post, $send);
    }

    #[AsyncMethod]
    public function broadcastAll(CrudRequestContract $request): JsonResponse
    {
        $post = $request->getResource()->getItem();

        if (! $post instanceof VkPost) {
            return JsonResponse::make()->toast('Пост не найден.', ToastType::ERROR);
        }

        return $this->dispatchBroadcast($post);
    }

    public static function sendTestMessage(VkPost $post, SendVkPostToChatAction $send): JsonResponse
    {
        if (trim((string) $post->broadcastText) === '') {
            return JsonResponse::make()->toast('У поста нет текста для отправки.', ToastType::ERROR);
        }

        $email = app(SiteSettings::class)->test_user_email;
        $user = $email ? User::query()->where('email', $email)->with('telegramChat')->first() : null;

        if ($user === null || $user->telegramChat === null) {
            return JsonResponse::make()->toast('Тестовый пользователь не найден или не привязал Telegram.', ToastType::ERROR);
        }

        try {
            $response = $send($post, $user->telegramChat);
        } catch (Throwable $e) {
            return JsonResponse::make()->toast("Ошибка отправки: {$e->getMessage()}", ToastType::ERROR);
        }

        if (! $response->telegraphOk()) {
            $error = $response->json('description') ?? ('HTTP '.$response->status());

            return JsonResponse::make()->toast("Ошибка отправки: {$error}", ToastType::ERROR);
        }

        return JsonResponse::make()->toast('Тестовое сообщение отправлено.', ToastType::SUCCESS);
    }

    public static function dispatchBroadcast(VkPost $post): JsonResponse
    {
        if (trim((string) $post->broadcastText) === '') {
            return JsonResponse::make()->toast('У поста нет текста для отправки.', ToastType::ERROR);
        }

        if (TelegramBot::current() === null) {
            return JsonResponse::make()->toast('Telegram-бот не настроен.', ToastType::ERROR);
        }

        $recipients = User::query()
            ->whereHas('telegramChat')
            ->whereDoesntHave('profile', fn ($q) => $q->where('is_bot_active', false))
            ->count();

        BroadcastVkPostJob::dispatch($post->id);

        return JsonResponse::make()->toast("Рассылка поставлена в очередь. Получателей: {$recipients}.", ToastType::SUCCESS);
    }
}
