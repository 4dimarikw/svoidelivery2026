<?php

namespace Tests\Feature\Auth;

use App\Events\Auth\TelegramSignatureInvalid;
use DefStudio\Telegraph\Facades\Telegraph;
use Domain\Auth\Models\User;
use Domain\Profile\Models\Profile;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Models\TelegramChat;
use Domain\Telegram\Support\TelegramLinkCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Два независимых механизма (см. CLAUDE.md, "Telegram login"):
 *  - Login Widget → auth.telegram.callback — вход/регистрация гостя;
 *  - диплинк /start → POST /telegraph/{token}/webhook — привязка Telegram
 *    к уже авторизованному аккаунту, обрабатывает App\Telegraph\WebhookHandler.
 *
 * Оба тестируются без реального бота: Login Widget подписывается вручную тем
 * же алгоритмом, что и SocialiteProviders\Telegram\Provider::user(); webhook
 * дёргается напрямую тем же payload'ом, что прислал бы Telegram, а исходящие
 * ответы бота перехватывает Telegraph::fake().
 */
class TelegramLoginTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'test-bot-token';

    private TelegramBot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        // TelegramBot::booted() шлёт живой запрос в Telegram API за
        // username при создании/смене токена — но не в тестах
        // (app()->runningUnitTests() гвард), так что это безопасно.
        $this->bot = TelegramBot::create([
            'token' => self::BOT_TOKEN,
            'name' => 'Test Bot',
            'username' => 'svoi_test_bot',
        ]);

        // Лимитер throttle:10,1 переживает границы тестов внутри одного
        // процесса — иначе последние кейсы получали бы 429.
        RateLimiter::clear('');
    }

    /**
     * Повторяет схему подписи из SocialiteProviders\Telegram\Provider::user():
     * hash_hmac по отсортированным "key=value", ключ — sha256 от токена бота.
     *
     * $without удаляет ключи ДО подписи, не после — в отличие от unset()
     * постфактум (см. test_missing_auth_date_is_rejected), это меняет сам
     * hash, а не просто убирает поле из запроса с уже невалидной подписью.
     * auth_date можно снимать постфактум, потому что его контроллер проверяет
     * раньше вызова Socialite и обрывается сразу; для остальных полей это не
     * так — Provider::user() хеширует все присланные ключи.
     *
     * @param  array<string, mixed>  $overrides
     * @param  list<string>  $without
     * @return array<string, mixed>
     */
    private function signedPayload(array $overrides = [], string $token = self::BOT_TOKEN, array $without = []): array
    {
        $data = array_diff_key(array_merge([
            'id' => 987654321,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
            'username' => 'ivan_petrov',
            'auth_date' => now()->timestamp,
        ], $overrides), array_flip($without));

        $dataToHash = collect($data)
            ->transform(fn ($value, $key) => "$key=$value")
            ->sort()
            ->join("\n");

        $data['hash'] = hash_hmac('sha256', $dataToHash, hash('sha256', $token, true));

        return $data;
    }

    // ---- Login Widget (гость) ----------------------------------------

    public function test_first_login_creates_a_user_and_a_linked_chat(): void
    {
        // Регистрация поднимает Registered → штатный
        // SendEmailVerificationNotification. У аккаунта нет email, и без
        // заглушки в User::sendEmailVerificationNotification() это упало бы.
        Notification::fake();

        $response = $this->get(route('auth.telegram.callback', $this->signedPayload()));

        $response->assertRedirect(config('fortify.home'));

        $user = User::query()->whereHas('telegramChat', fn ($q) => $q->where('chat_id', 987654321))->sole();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email);
        $this->assertNull($user->password);
        $this->assertSame('Иван Петров', $user->name);

        // App\Listeners\CreateUserProfile отработал на Registered.
        $this->assertNotNull($user->profile);
        // signedPayload() кладёт username => 'ivan_petrov' — при регистрации
        // через Telegram это должно сразу попасть в social_links['telegram'] профиля.
        $this->assertSame('https://t.me/ivan_petrov', $user->profile->socialLink('telegram'));

        Notification::assertNothingSent();
    }

    public function test_registration_without_a_username_leaves_telegram_url_empty(): void
    {
        Notification::fake();

        $payload = $this->signedPayload(without: ['username']);

        $response = $this->get(route('auth.telegram.callback', $payload));

        $response->assertRedirect(config('fortify.home'));

        $user = User::query()->whereHas('telegramChat', fn ($q) => $q->where('chat_id', 987654321))->sole();

        $this->assertNull($user->profile->socialLink('telegram'));
    }

    public function test_repeat_login_reuses_the_same_user(): void
    {
        $existing = User::factory()->telegram()->create();
        $profile = Profile::factory()->for($existing)->create(['social_links' => null]);
        TelegramChat::create([
            'chat_id' => '987654321',
            'telegraph_bot_id' => $this->bot->id,
            'user_id' => $existing->id,
        ]);

        // signedPayload() шлёт username => 'ivan_petrov' — повторный вход не
        // должен затирать social_links['telegram'] существующего профиля
        // (register() на этом пути вообще не вызывается).
        $this->get(route('auth.telegram.callback', $this->signedPayload()));

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::query()->count());
        $this->assertNull($profile->fresh()->socialLink('telegram'));
    }

    public function test_tampered_hash_is_rejected(): void
    {
        Event::fake([TelegramSignatureInvalid::class]);

        $payload = $this->signedPayload();
        $payload['first_name'] = 'Мимо';

        $response = $this->from(route('login'))
            ->get(route('auth.telegram.callback', $payload));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(0, User::query()->count());

        // Единственная причина отказа входа, которая законно не должна
        // встречаться при нормальной работе — попадает в event_logs
        // (см. App\Http\Controllers\Auth\TelegramLoginController::logAuthFailure()).
        Event::assertDispatched(TelegramSignatureInvalid::class, fn (TelegramSignatureInvalid $e) => $e->source === 'widget');
    }

    public function test_payload_signed_with_a_foreign_token_is_rejected(): void
    {
        Event::fake([TelegramSignatureInvalid::class]);

        $payload = $this->signedPayload(token: 'someone-elses-token');

        $this->get(route('auth.telegram.callback', $payload));

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
        Event::assertDispatched(TelegramSignatureInvalid::class);
    }

    public function test_stale_auth_date_is_rejected(): void
    {
        // Драйвер сам пропускает целые сутки ('before:1 day') — окно сужено
        // до минуты в TelegramLoginController::AUTH_DATE_TTL.
        $payload = $this->signedPayload(['auth_date' => now()->subMinutes(5)->timestamp]);

        $this->get(route('auth.telegram.callback', $payload));

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_missing_auth_date_is_rejected(): void
    {
        $payload = $this->signedPayload();
        unset($payload['auth_date']);

        $this->get(route('auth.telegram.callback', $payload));

        $this->assertGuest();
    }

    public function test_login_page_shows_the_widget_only_when_a_bot_is_configured(): void
    {
        $response = $this->get(route('login'));

        $response->assertSee('data-telegram-login="svoi_test_bot"', false);
        // telegramAuth (resources/js/telegram.js) переключает виджет и
        // Mini App-кнопку — обе точки входа рендерятся под одним корнем.
        $response->assertSee('x-data="telegramAuth"', false);
        $response->assertSee(route('auth.telegram.webapp'), false);

        TelegramBot::query()->delete();
        TelegramBot::flushCache();

        $response = $this->get(route('login'));
        $response->assertDontSee('telegram-widget.js', false);
        $response->assertDontSee('x-data="telegramAuth"', false);
    }

    // ---- Диплинк /start (привязка авторизованного аккаунта) ----------

    /**
     * Минимальный Telegram Update: приватное сообщение "/start <code>".
     *
     * @return array<string, mixed>
     */
    private function startUpdatePayload(string $code, int $telegramId = 555000111): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'date' => now()->timestamp,
                'text' => "/start {$code}",
                'chat' => ['id' => (string) $telegramId, 'type' => 'private'],
                'from' => ['id' => $telegramId, 'first_name' => 'Пётр', 'username' => 'petr_tg'],
            ],
        ];
    }

    public function test_start_with_a_valid_code_links_telegram_to_the_user(): void
    {
        Telegraph::fake();

        $user = User::factory()->create();
        $code = TelegramLinkCode::issue($user);

        $this->postJson(route('telegraph.webhook', ['token' => $this->bot->token]), $this->startUpdatePayload($code))
            ->assertNoContent();

        $chat = TelegramChat::query()->where('chat_id', '555000111')->sole();

        $this->assertSame($user->id, $chat->user_id);
        Telegraph::assertSent(__('telegram.linked'));
    }

    public function test_start_with_an_invalid_code_links_nobody(): void
    {
        Telegraph::fake();

        $this->postJson(route('telegraph.webhook', ['token' => $this->bot->token]), $this->startUpdatePayload('not-a-real-code'))
            ->assertNoContent();

        // store_unknown_chats_in_db выключен (config/telegraph.php), а
        // WebhookHandler::start() сам ничего не сохраняет, если код не
        // резолвится — значит и строки в telegraph_chats быть не должно.
        $this->assertSame(0, TelegramChat::query()->where('chat_id', '555000111')->count());
        Telegraph::assertSent(__('telegram.link_code_invalid'));
    }

    public function test_start_code_is_single_use(): void
    {
        Telegraph::fake();

        $first = User::factory()->create();
        $second = User::factory()->create();
        $code = TelegramLinkCode::issue($first);

        $this->postJson(route('telegraph.webhook', ['token' => $this->bot->token]), $this->startUpdatePayload($code));
        $this->postJson(route('telegraph.webhook', ['token' => $this->bot->token]), $this->startUpdatePayload($code, telegramId: 777));

        $this->assertSame($first->id, TelegramChat::query()->where('chat_id', '555000111')->sole()->user_id);
        $this->assertSame(0, TelegramChat::query()->where('chat_id', '777')->count());
        $this->assertNotSame($second->id, $first->id);
    }

    // ---- Отвязка --------------------------------------------------------

    public function test_unlink_is_forbidden_when_telegram_is_the_only_way_in(): void
    {
        $user = User::factory()->telegram()->create();
        TelegramChat::create(['chat_id' => '1', 'telegraph_bot_id' => $this->bot->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('account.telegram.unlink'))
            ->assertForbidden();

        $this->assertTrue($user->fresh()->telegramLinked());
    }

    public function test_unlink_succeeds_when_a_password_and_email_remain(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);
        TelegramChat::create(['chat_id' => '1', 'telegraph_bot_id' => $this->bot->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('account.telegram.unlink'));

        $response->assertRedirect();
        $this->assertFalse($user->fresh()->telegramLinked());
    }

    // ---- hasVerifiedEmail / профиль без email ----------------------------

    public function test_telegram_user_passes_the_verified_middleware(): void
    {
        // User::hasVerifiedEmail() возвращает true при email === null:
        // подтверждать нечего. MustVerifyEmail и middleware('verified')
        // при этом остаются на месте.
        $user = User::factory()->telegram()->create();

        $this->actingAs($user)->get(route('account.profile.edit'))->assertOk();
    }

    public function test_ordinary_unverified_user_is_still_bounced(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('account.profile.edit'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_telegram_user_can_update_their_name_without_an_email(): void
    {
        $user = User::factory()->telegram()->create();

        $this->actingAs($user)
            ->put(route('user-profile-information.update'), ['name' => 'Новое имя'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Новое имя', $user->fresh()->name);
        $this->assertNull($user->fresh()->email);
    }

    public function test_a_normal_user_cannot_blank_their_email(): void
    {
        // Для обычного (не-telegram) пользователя email обязателен — то же
        // поведение, что было до появления Telegram-аккаунтов. requiredIf
        // проверяется по текущему email ($user->email !== null), поэтому
        // ничего не откатывается частично: невалидная отправка отклоняется
        // целиком, включая имя.
        $user = User::factory()->create();
        $originalEmail = $user->email;
        $originalName = $user->name;

        $response = $this->actingAs($user)
            ->put(route('user-profile-information.update'), ['name' => 'Новое имя', 'email' => '']);

        $response->assertSessionHasErrors('email', null, 'updateProfileInformation');
        $this->assertSame($originalEmail, $user->fresh()->email);
        $this->assertSame($originalName, $user->fresh()->name);
    }
}
