<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkPost\Pages;

use App\Events\VkPostTestSend;
use App\MoonShine\Resources\VkPost\VkPostResource;
use App\MoonShine\Support\MoonshineTelegramLink;
use Domain\Auth\Models\User;
use Domain\Telegram\Models\TelegramBot;
use Domain\Vk\Actions\DownloadVkPostImages;
use Domain\Vk\Actions\SendVkPostToChatAction;
use Domain\Vk\Enums\VkImageSendMode;
use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Infrastructure\Jobs\BroadcastVkPostJob;
use Infrastructure\Settings\VKSyncSettings;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\HttpMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
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

        $isAdmin = request()->user()->isSuperUser();

        return [
            ID::make()->sortable(),

            Date::make('Опубликован в VK', 'posted_at')->format('d.m.Y H:i')->sortable()->canSee(fn () => $isAdmin),

            Text::make('Текст', formatted: fn (VkPost $item) => Str::limit((string) ($item->message_text ?: $item->text), 80)),
            Text::make('Загружено на сайт', formatted: fn (VkPost $item) => $item->imageDownloadStatus()),
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

    /**
     * @param  TableBuilder  $component
     * @return TableBuilder
     */
    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component
            ->columnSelection()
            ->sticky()
            ->stickyButtons();
    }

    protected function buttons(): ListOf
    {
        $events = [AlpineJs::event(JsEvent::TABLE_UPDATED, $this->getListComponentName())];

        return parent::buttons()->add(...[
            ActionButton::make('')
                ->icon('arrow-down-tray')
                ->showInLine()
                ->canSee(fn () => $this->getResource()->can(Ability::UPDATE))
                ->method('downloadImages', events: $events)
                ->async(HttpMethod::POST, events: $events)->setAttribute('data-async-method', 'POST'),
            ActionButton::make('')
                ->icon('paper-airplane')
                ->showInLine()
                ->method('sendToSelf', events: $events)
                ->canSee(fn () => $this->getResource()->can(Ability::UPDATE))
                ->setAttribute('data-async-method', 'POST')
                ->async(HttpMethod::POST, events: $events)
                ->withConfirm(
                    title: 'Отправить себе',
                    content: 'Тестовое сообщение уйдёт в ваш Telegram, привязанный на странице профиля.',
                    button: 'Отправить',
                ),
            ActionButton::make('')
                ->icon('paper-airplane')
                ->showInLine()
                ->warning()
                ->method('broadcastAll', events: $events)
                ->canSee(fn () => $this->getResource()->can(Ability::UPDATE))
                ->setAttribute('data-async-method', 'POST')
                ->async(HttpMethod::POST, events: $events)
                ->withConfirm(
                    title: 'Разослать всем',
                    content: 'Сообщение уйдёт всем пользователям с привязанным Telegram. Отменить рассылку нельзя.',
                    button: 'Разослать',
                ),
        ]);
    }

    #[AsyncMethod]
    public function downloadImages(CrudRequestContract $request, DownloadVkPostImages $download): JsonResponse
    {
        return self::downloadPostImages($request, $download);
    }

    public static function downloadPostImages(CrudRequestContract $request, DownloadVkPostImages $download): JsonResponse
    {
        $resource = $request->getResource();
        if (! $resource->can(Ability::UPDATE)) {
            return JsonResponse::make()->toast('Нет права изменять пост.', ToastType::ERROR, 10000)->setStatusCode(403);
        }
        $post = $resource->getItem();
        if (! $post instanceof VkPost) {
            return JsonResponse::make()->toast('Пост не найден.', ToastType::ERROR, 10000);
        }
        try {
            $download($post);
        } catch (Throwable $e) {
            return JsonResponse::make()->toast('Ошибка загрузки: '.$e->getMessage(), ToastType::ERROR, 10000);
        }

        return JsonResponse::make()->toast('Картинки загружены на сайт: '.$post->imageDownloadStatus(), ToastType::SUCCESS);
    }

    #[AsyncMethod]
    public function sendToSelf(CrudRequestContract $request, SendVkPostToChatAction $send): JsonResponse
    {
        $post = $request->getResource()->getItem();

        if (! $post instanceof VkPost) {
            return JsonResponse::make()->toast('Пост не найден.', ToastType::ERROR, 10000);
        }

        return $this->sendTestMessage($post, $send);
    }

    #[AsyncMethod]
    public function broadcastAll(CrudRequestContract $request): JsonResponse
    {
        $post = $request->getResource()->getItem();

        if (! $post instanceof VkPost) {
            return JsonResponse::make()->toast('Пост не найден.', ToastType::ERROR, 10000);
        }

        return $this->dispatchBroadcast($post);
    }

    public static function sendTestMessage(VkPost $post, SendVkPostToChatAction $send): JsonResponse
    {
        $mode = app(VKSyncSettings::class)->image_send_mode;
        if (trim((string) $post->broadcastText) === '') {
            event(new VkPostTestSend($post->id, 'skipped', 'У поста нет текста для отправки.', $mode));

            return JsonResponse::make()->toast('У поста нет текста для отправки.', ToastType::ERROR);
        }

        $chat = MoonshineTelegramLink::chatFor((int) request()->user()->id);

        if ($chat === null) {
            event(new VkPostTestSend($post->id, 'skipped', 'Привяжите Telegram на странице профиля.', $mode));

            return JsonResponse::make()->toast('Привяжите Telegram на странице профиля.', ToastType::ERROR);
        }

        $prepared = null;
        try {
            $prepared = $send->prepare($post);
            $mode = $prepared->mode->value;
            $response = $send($post, $chat, $prepared);
        } catch (Throwable $e) {
            event(new VkPostTestSend($post->id, 'failed', $e->getMessage(), $mode, $prepared?->imageUrls ?? []));

            return JsonResponse::make()->toast("Ошибка отправки: {$e->getMessage()}", ToastType::ERROR);
        }

        if (! $response->telegraphOk()) {
            $error = $response->json('description') ?? ('HTTP '.$response->status());
            event(new VkPostTestSend($post->id, 'failed', $error, $mode, $prepared->imageUrls));

            return JsonResponse::make()->toast("Ошибка отправки: {$error}", ToastType::ERROR);
        }

        event(new VkPostTestSend($post->id, 'sent', imageSendMode: $mode, imageUrls: $prepared->imageUrls));

        return JsonResponse::make()->toast('Тестовое сообщение отправлено.', ToastType::SUCCESS);
    }

    public static function dispatchBroadcast(VkPost $post): JsonResponse
    {
        if (app(VKSyncSettings::class)->image_send_mode === VkImageSendMode::SiteFile->value) {
            return JsonResponse::make()->toast(BroadcastVkPostJob::FILE_MODE_ERROR, ToastType::ERROR, 10000);
        }

        if (trim((string) $post->broadcastText) === '') {
            return JsonResponse::make()->toast('У поста нет текста для отправки.', ToastType::ERROR, 10000);
        }

        if (TelegramBot::current() === null) {
            return JsonResponse::make()->toast('Telegram-бот не настроен.', ToastType::ERROR, 10000);
        }

        $recipients = User::query()
            ->whereHas('telegramChat')
            ->whereDoesntHave('profile', fn ($q) => $q->where('is_bot_active', false))
            ->count();

        BroadcastVkPostJob::dispatch($post->id);

        return JsonResponse::make()->toast("Рассылка поставлена в очередь. Получателей: {$recipients}.", ToastType::SUCCESS);
    }
}
