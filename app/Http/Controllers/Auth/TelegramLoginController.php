<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\User;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Models\TelegramChat;
use Domain\Telegram\Support\WebAppInitData;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

/**
 * Вход гостя через Telegram — два независимых способа получить те же
 * поля (id/имя): Login Widget (обычный браузер) и Mini App initData
 * (сайт открыт внутри Telegram Web App, где виджет не работает — он
 * рендерится в iframe от oauth.telegram.org и требует redirect на домен из
 * BotFather /setdomain, что внутри webview Telegram либо блокируется, либо
 * ломает сессию Mini App). См. <x-ui.telegram-login-button> /
 * <x-ui.telegram-webapp-button> — auth/login.blade.php показывает только
 * подходящий вариант через Alpine (telegramAuth, resources/js/telegram.js).
 *
 * Оба способа — только вход/регистрация — привязка Telegram к уже
 * авторизованному аккаунту идёт третьим, отдельным путём: через диплинк
 * t.me/<bot>?start=<code> и App\Telegraph\WebhookHandler::start() (см.
 * Domain\Telegram\Support\TelegramLinkCode, /account/profile). Причина
 * раздвоения: боту нужен реальный разговор с пользователем, чтобы потом
 * слать ему сообщения — диплинк это гарантирует всегда, виджет только если
 * пользователь разрешил data-request-access="write". Гостю диплинк не
 * подходит: он не даёт немедленного редиректа с сессией.
 *
 * Виджет — не OAuth: он редиректит браузер прямо сюда обычным GET с
 * параметрами id/first_name/last_name/username/photo_url/auth_date/hash, а
 * SocialiteProviders\Telegram\Provider::user() проверяет их подпись.
 */
class TelegramLoginController extends Controller
{
    /**
     * Окно, в течение которого подпись Telegram считается свежей.
     *
     * Сам драйвер проверяет auth_date правилом 'before:1 day' — это сутки на
     * повтор ссылки, которая при этом оседает в истории браузера и в Referer.
     * Здесь окно сужается до минуты: легитимный редирект от Telegram
     * укладывается в секунды.
     */
    private const AUTH_DATE_TTL = 60;

    public function callback(Request $request): RedirectResponse
    {
        $bot = TelegramBot::current();

        if ($bot === null) {
            return redirect()->route('login')
                ->withErrors(['email' => __('account.telegram.failed')]);
        }

        $telegramUser = $this->resolveTelegramUser($request, $bot);

        if ($telegramUser === null) {
            // Ключ 'email' — тот же, что у формы входа, чтобы ошибку показал
            // уже стоящий там <x-ui.error name="email">.
            return redirect()->route('login')
                ->withErrors(['email' => __('account.telegram.failed')]);
        }

        $this->loginTelegramUser(
            $request,
            (int) $telegramUser->getId(),
            $telegramUser->getName() ?: $telegramUser->getNickname(),
            $bot,
        );

        return redirect()->intended(config('fortify.home'));
    }

    /**
     * Вход через Mini App: initData вместо GET-параметров виджета, другой
     * алгоритм подписи (см. Domain\Telegram\Support\WebAppInitData).
     */
    public function webapp(Request $request): RedirectResponse
    {
        $bot = TelegramBot::current();

        $data = $bot ? WebAppInitData::verify((string) $request->input('init_data'), $bot->token) : null;

        if ($data === null) {
            // Тот же ключ ошибки, что у callback() — тот же <x-ui.error> на
            // форме входа его покажет.
            return redirect()->route('login')
                ->withErrors(['email' => __('account.telegram.failed')]);
        }

        $this->loginTelegramUser($request, $data->id, $data->displayName(), $bot);

        return redirect()->intended(config('fortify.home'));
    }

    /**
     * Отвязка. Дуальный ответ (пустой 200 на JSON / redirect-back иначе) —
     * тот же контракт, что у Account\ProfileController::update(). Чат не
     * удаляется, только отвязывается user_id — боту он ещё может пригодиться.
     */
    public function unlink(Request $request): RedirectResponse|JsonResponse
    {
        // Без пароля и email Telegram — единственный способ войти. Отвязка
        // заперла бы аккаунт навсегда.
        abort_unless($request->user()->canUnlinkTelegram(), 403);

        $request->user()->telegramChat?->update(['user_id' => null]);

        if ($request->wantsJson()) {
            return response()->json([], 200);
        }

        return back()->with('status', 'telegram-unlinked');
    }

    /**
     * Проверяет подпись Telegram. null — payload невалиден или протух.
     */
    private function resolveTelegramUser(Request $request, TelegramBot $bot): ?SocialiteUser
    {
        // auth_date проверяется до драйвера: тот пропускает целые сутки.
        $authDate = (int) $request->input('auth_date');

        if ($authDate <= 0 || now()->timestamp - $authDate > self::AUTH_DATE_TTL) {
            return null;
        }

        // config('services.telegram.*') читает SocialiteProviders\Manager —
        // заполняется локально, прямо перед вызовом, а не глобально в
        // AppServiceProvider::boot() (тот дёргался бы на КАЖДЫЙ запрос ещё
        // до гарантированного применения миграций — см. комментарий там).
        config([
            'services.telegram.client_id' => (string) $bot->id,
            'services.telegram.client_secret' => $bot->token,
            'services.telegram.redirect' => url('/auth/telegram/callback'),
        ]);

        try {
            // Provider::user() кидает голый \InvalidArgumentException и на
            // непрошедшей валидации полей, и на несовпадении HMAC.
            return Socialite::driver('telegram')->user();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Общий хвост обоих способов входа: находит/заводит telegraph_chats по
     * chat_id, логинит пользователя, поднимает сессию.
     */
    private function loginTelegramUser(Request $request, int $chatId, string $name, TelegramBot $bot): void
    {
        $chat = TelegramChat::query()
            ->where('chat_id', $chatId)
            ->where('telegraph_bot_id', $bot->id)
            ->first();

        $user = $chat?->user_id ? $chat->user : $this->register($chatId, $name, $bot, $chat);

        Auth::login($user, true);
        $request->session()->regenerate();
    }

    /**
     * Создаёт аккаунт без email и пароля — Telegram их не отдаёт — и
     * привязывает (или заводит) telegraph_chats к нему.
     */
    private function register(int $chatId, string $name, TelegramBot $bot, ?TelegramChat $chat): User
    {
        $user = User::create(['name' => $name]);

        // App\Listeners\CreateUserProfile — единственное место, которое знает,
        // что происходит после регистрации; заводить профиль здесь руками
        // означало бы продублировать этот контракт. Штатный
        // SendEmailVerificationNotification на этом событии безопасен: см.
        // User::sendEmailVerificationNotification().
        event(new Registered($user));

        ($chat ?? new TelegramChat(['chat_id' => $chatId, 'telegraph_bot_id' => $bot->id]))
            ->fill(['user_id' => $user->id, 'name' => $name])
            ->save();

        return $user;
    }
}
