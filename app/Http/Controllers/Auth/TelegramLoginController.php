<?php

namespace App\Http\Controllers\Auth;

use App\Events\Auth\TelegramSignatureInvalid;
use App\Http\Controllers\Controller;
use Domain\Telegram\Actions\FindOrRegisterTelegramUser;
use Domain\Telegram\Actions\UnlinkTelegramAccount;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Support\TelegramAuthFields;
use Domain\Telegram\Support\TelegramLinkCode;
use Domain\Telegram\Support\WebAppInitData;
use Domain\Telegram\Support\WebAppSessionExchange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\User;

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
 * параметрами id/first_name/last_name/username/photo_url/auth_date/hash.
 * Подпись проверяется локально через hash_equals; DTO Socialite сохранён.
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
            // DTO, который создаёт локальная проверка подписи.
            $telegramUser instanceof User ? ($telegramUser->getRaw()['username'] ?? null) : null,
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
        $data = WebAppInitData::verify($request->input('init_data'), $bot->token, $reason);

        if ($data === null) {
            // Тот же ключ ошибки, что у callback() — тот же <x-ui.error> на
            // форме входа его покажет.
            $this->logAuthFailure($request, 'webapp', $reason ?? 'unknown');

            return redirect()->route('login')
                ->withErrors(['email' => __('account.telegram.failed')]);
        }

        if (! app(WebAppSessionExchange::class)->claim($data, $bot->id, $request->session())) {
            $this->logAuthFailure($request, 'webapp', 'exchange_rejected');

            return redirect()->route('login')->withErrors(['email' => __('account.telegram.exchange_failed')]);
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
        app(UnlinkTelegramAccount::class)
            ->handle(TelegramLinkCode::SUBJECT_USER, $request->user()->id);

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

    /** @return array{0: ?SocialiteUser, 1: ?string} */
    private function resolveTelegramUser(Request $request, TelegramBot $bot): array
    {
        $fields = $request->all();
        if (! TelegramAuthFields::scalarFields($fields)
            || TelegramAuthFields::positiveInteger($fields['id'] ?? null) === null
            || ! is_string($fields['hash'] ?? null)
            || ! preg_match('/\\A[a-f0-9]{64}\\z/D', $fields['hash'])) {
            return [null, 'field_validation_failed'];
        }
        foreach (['first_name', 'last_name', 'username', 'photo_url'] as $field) {
            if (isset($fields[$field]) && ! is_string($fields[$field])) {
                return [null, 'field_validation_failed'];
            }
        }
        $reason = TelegramAuthFields::dateFailure($fields['auth_date'] ?? null, self::AUTH_DATE_TTL);
        if ($reason !== null) {
            return [null, $reason];
        }
        $hash = $fields['hash'];
        unset($fields['hash']);
        $check = collect($fields)->map(fn ($value, $key) => "$key=$value")->sort()->join("\n");
        $expected = hash_hmac('sha256', $check, hash('sha256', $bot->token, true));
        if (! hash_equals($expected, $hash)) {
            return [null, 'hash_mismatch'];
        }
        unset($fields['auth_date']);
        $name = trim(($fields['first_name'] ?? '').' '.($fields['last_name'] ?? ''));

        return [(new User)->setRaw($fields)->map([
            'id' => $fields['id'],
            'name' => $name !== '' ? $name : ($fields['username'] ?? (string) $fields['id']),
            'nickname' => $fields['username'] ?? $fields['first_name'] ?? null,
            'avatar' => $fields['photo_url'] ?? null,
        ]), null];
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
        $user = app(FindOrRegisterTelegramUser::class)
            ->handle($bot->id, $chatId, $name, $username);

        Auth::login($user, true);
        $request->session()->regenerate();
    }
}
