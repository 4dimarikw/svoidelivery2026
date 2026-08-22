<?php

namespace App\Http\Controllers\Auth;

use App\Events\Auth\TelegramSignatureInvalid;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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
            $this->logAuthFailure($request, 'widget', 'no_active_bot');

            return redirect()->route('login')
                ->withErrors(['email' => __('account.telegram.failed')]);
        }

        [$telegramUser, $reason] = $this->resolveTelegramUser($request, $bot);

        if ($telegramUser === null) {
            $this->logAuthFailure($request, 'widget', $reason ?? 'unknown');

            // Ключ 'email' — тот же, что у формы входа, чтобы ошибку показал
            // уже стоящий там <x-ui.error name="email">.
            return redirect()->route('login')
                ->withErrors(['email' => __('account.telegram.failed')]);
        }

        $this->loginTelegramUser(
            $request,
            (int) $telegramUser->getId(),
            $telegramUser->getName() ?: $telegramUser->getNickname(),
            // getNickname() тут не годится — Provider::mapUserToObject()
            // фолбэчит его на first_name, когда у аккаунта нет @username.
            // Сырой payload честный: ключа 'username' там просто нет.
            // getRaw() не в Contracts\User — instanceof сужает до конкретного
            // класса, который реально возвращает Socialite::driver('telegram').
            $telegramUser instanceof \Laravel\Socialite\Two\User ? ($telegramUser->getRaw()['username'] ?? null) : null,
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

        if ($bot === null) {
            $this->logAuthFailure($request, 'webapp', 'no_active_bot');

            return redirect()->route('login')
                ->withErrors(['email' => __('account.telegram.failed')]);
        }

        $reason = null;
        $data = WebAppInitData::verify((string) $request->input('init_data'), $bot->token, $reason);

        if ($data === null) {
            // Тот же ключ ошибки, что у callback() — тот же <x-ui.error> на
            // форме входа его покажет.
            $this->logAuthFailure($request, 'webapp', $reason ?? 'unknown');

            return redirect()->route('login')
                ->withErrors(['email' => __('account.telegram.failed')]);
        }

        $this->loginTelegramUser($request, $data->id, $data->displayName(), $data->username, $bot);

        // redirect_to приходит от <x-ui.telegram-autologin> — юзер,
        // залогиненный посреди обычной страницы, должен на неё и
        // вернуться, а не на fortify.home. Кнопка на /login redirect_to не
        // шлёт, так что для неё поведение не меняется.
        $target = $this->safeRedirectTarget($request, $request->input('redirect_to'));

        return $target !== null
            ? redirect()->to($target)
            : redirect()->intended(config('fortify.home'));
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
     * Только локальный путь, начинающийся ровно с одного '/' — иначе это
     * open redirect. '//evil.com' и '/\evil.com' браузер трактует как
     * protocol-relative URL на чужой хост, оба отсекаются тем же regex.
     * Любое отклонение от формата — null, тихий откат на fortify.home, не
     * ошибка (значение приходит из скрытой формы, а не от пользователя).
     * Строку в security-канал пишем только когда значение реально было
     * передано и отклонено — обычный случай "redirect_to не передан" не
     * должен засорять журнал.
     */
    private function safeRedirectTarget(Request $request, mixed $redirectTo): ?string
    {
        if (! is_string($redirectTo) || $redirectTo === '') {
            return null;
        }

        if (mb_strlen($redirectTo) <= 255 && preg_match('#^/[^/\\\\]#', $redirectTo) === 1) {
            return $redirectTo;
        }

        Log::channel('security')->warning('telegram autologin redirect_to rejected', [
            'ip' => $request->ip(),
            'route' => $request->route()?->getName(),
        ]);

        return null;
    }

    /**
     * Проверяет подпись Telegram.
     *
     * @return array{0: ?SocialiteUser, 1: ?string} [пользователь, причина отказа]
     *                                              Причина: 'stale_auth_date' | 'field_validation_failed' | 'hash_mismatch'.
     *                                              Provider::user() (vendor) кидает один и тот же голый
     *                                              \InvalidArgumentException и на непрошедшей валидации полей, и на
     *                                              несовпадении HMAC — чтобы различить их без правки vendor-кода,
     *                                              та же валидация полей прогоняется здесь заранее: если она
     *                                              проходит, а драйвер всё равно бросает, единственная оставшаяся
     *                                              причина — несовпадение подписи.
     */
    private function resolveTelegramUser(Request $request, TelegramBot $bot): array
    {
        // auth_date проверяется до драйвера: тот пропускает целые сутки.
        $authDate = (int) $request->input('auth_date');

        if ($authDate <= 0 || now()->timestamp - $authDate > self::AUTH_DATE_TTL) {
            return [null, 'stale_auth_date'];
        }

        $fieldsValid = ! Validator::make($request->all(), [
            'id' => 'required|numeric',
            'auth_date' => 'required|date_format:U|before:1 day',
            'hash' => 'required|size:64',
        ])->fails();

        if (! $fieldsValid) {
            return [null, 'field_validation_failed'];
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
            return [Socialite::driver('telegram')->user(), null];
        } catch (\InvalidArgumentException) {
            return [null, 'hash_mismatch'];
        }
    }

    /**
     * Отказ входа через Telegram — всегда в security-канал; hash_mismatch
     * дополнительно попадает в event_logs (App\Events\Auth\TelegramSignatureInvalid) —
     * единственная причина здесь, которая законно не должна встречаться при
     * нормальной работе (протухшая auth_date/отсутствующий бот — обычный шум).
     */
    private function logAuthFailure(Request $request, string $source, string $reason): void
    {
        Log::channel('security')->warning('telegram login rejected', [
            'source' => $source,
            'reason' => $reason,
            'ip' => $request->ip(),
            'route' => $request->route()?->getName(),
        ]);

        if ($reason === 'hash_mismatch') {
            event(new TelegramSignatureInvalid($source, $request->ip()));
        }
    }

    /**
     * Общий хвост обоих способов входа: находит/заводит telegraph_chats по
     * chat_id, логинит пользователя, поднимает сессию.
     */
    private function loginTelegramUser(Request $request, int $chatId, string $name, ?string $username, TelegramBot $bot): void
    {
        $chat = TelegramChat::query()
            ->where('chat_id', $chatId)
            ->where('telegraph_bot_id', $bot->id)
            ->first();

        $user = $chat?->user_id ? $chat->user : $this->register($chatId, $name, $username, $bot, $chat);

        Auth::login($user, true);
        $request->session()->regenerate();
    }

    /**
     * Создаёт аккаунт без email и пароля — Telegram их не отдаёт — и
     * привязывает (или заводит) telegraph_chats к нему.
     */
    private function register(int $chatId, string $name, ?string $username, TelegramBot $bot, ?TelegramChat $chat): User
    {
        $user = User::create(['name' => $name]);

        // App\Listeners\CreateUserProfile — единственное место, которое знает,
        // что происходит после регистрации; заводить профиль здесь руками
        // означало бы продублировать этот контракт. Штатный
        // SendEmailVerificationNotification на этом событии безопасен: см.
        // User::sendEmailVerificationNotification().
        event(new Registered($user));

        // @username в Telegram не обязателен — без него ссылку строить не из
        // чего, social_links['telegram'] остаётся незаполненным и
        // редактируемым как раньше. Только при СОЗДАНИИ аккаунта: повторный
        // вход (см. loginTelegramUser выше — тогда register() вообще не
        // вызывается) не должен затирать то, что пользователь мог сам
        // поменять в профиле.
        if ($username !== null && $username !== '') {
            $user->profile()->updateOrCreate([], ['social_links' => ['telegram' => "https://t.me/{$username}"]]);
        }

        ($chat ?? new TelegramChat(['chat_id' => $chatId, 'telegraph_bot_id' => $bot->id]))
            ->fill(['user_id' => $user->id, 'name' => $name])
            ->save();

        return $user;
    }
}
