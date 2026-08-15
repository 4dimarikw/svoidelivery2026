<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\MoonShine\Support\MoonshineTelegramLink;
use Domain\Telegram\Models\TelegramBot;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Box;

/**
 * Расширяет вендорную ProfilePage единственным блоком — привязкой Telegram
 * админа (нужна для кнопки «Отправить себе» на VK-постах,
 * VkPostIndexPage::sendTestMessage()). fields()/getForm() не переопределяем:
 * ничего из нового блока не сохраняется через profile.store, привязка идёт
 * через бота (deep-link + /start), отвязка — отдельным async-методом.
 *
 * Регистрация — config/moonshine.php: pages.profile. uriKey не меняется
 * (kebab от basename класса — тот же "ProfilePage"), так что URL страницы
 * (/{prefix}/page/profile-page) остаётся прежним.
 */
final class ProfilePage extends \MoonShine\Laravel\Pages\ProfilePage
{
    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        return [
            $this->getForm(),
            $this->telegramBox(),
            ...$this->getPushedComponents(),
        ];
    }

    protected function telegramBox(): ComponentContract
    {
        $moonshineUserId = (int) request()->user()->id;
        $chat = MoonshineTelegramLink::chatFor($moonshineUserId);

        if ($chat !== null) {
            return Box::make('Telegram', [
                Heading::make("Привязан: {$chat->name} (chat_id: {$chat->chat_id})")->h(4),
                ActionButton::make('Отвязать')
                    ->icon('x-mark')
                    ->error()
                    ->method('unlinkTelegram', page: $this)
                    ->withConfirm(
                        title: 'Отвязать Telegram',
                        content: 'Кнопка «Отправить себе» на VK-постах перестанет работать, пока вы не привяжете Telegram заново.',
                        button: 'Отвязать',
                    ),
            ]);
        }

        $bot = TelegramBot::current();

        if ($bot?->username === null) {
            return Box::make('Telegram', [
                Heading::make('Telegram-бот не настроен.')->h(4),
            ]);
        }

        $code = MoonshineTelegramLink::issueCode($moonshineUserId);

        return Box::make('Telegram', [
            Heading::make('Не привязан. Нужен для кнопки «Отправить себе» на странице VK-постов.')->h(4),
            ActionButton::make('Привязать Telegram', "https://t.me/{$bot->username}?start={$code}")
                ->icon('link')
                ->primary()
                ->blank(),
        ]);
    }

    #[AsyncMethod]
    public function unlinkTelegram(): JsonResponse
    {
        MoonshineTelegramLink::unlink((int) request()->user()->id);

        return JsonResponse::make()
            ->toast('Telegram отвязан.', ToastType::SUCCESS)
            ->redirect($this->getRoute());
    }
}
