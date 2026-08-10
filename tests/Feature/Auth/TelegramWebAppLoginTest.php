<?php

namespace Tests\Feature\Auth;

use Domain\Auth\Models\User;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Models\TelegramChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Вход/регистрация гостя через Telegram Mini App — пара к
 * TelegramLoginTest (Login Widget). Другой алгоритм подписи
 * (Domain\Telegram\Support\WebAppInitData), но тот же общий хвост
 * (TelegramLoginController::loginTelegramUser): find-or-create chat →
 * find-or-register user → Auth::login().
 */
class TelegramWebAppLoginTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'test-bot-token';

    private TelegramBot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = TelegramBot::create([
            'token' => self::BOT_TOKEN,
            'name' => 'Test Bot',
            'username' => 'svoi_test_bot',
        ]);

        RateLimiter::clear('');
    }

    /**
     * Повторяет алгоритм из Domain\Telegram\Support\WebAppInitData::verify():
     * hash_hmac по отсортированным "key=value" (кроме hash), ключ —
     * HMAC_SHA256("WebAppData", token) — не sha256(token), как у виджета.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function signedInitData(array $overrides = [], string $token = self::BOT_TOKEN): string
    {
        $params = array_merge([
            'user' => json_encode(['id' => 987654321, 'first_name' => 'Иван', 'last_name' => 'Петров', 'username' => 'ivan_petrov'], JSON_UNESCAPED_UNICODE),
            'auth_date' => (string) now()->timestamp,
        ], $overrides);

        $dataCheckString = collect($params)
            ->sortKeys()
            ->map(fn ($value, $key) => "$key=$value")
            ->join("\n");

        $secretKey = hash_hmac('sha256', $token, 'WebAppData', true);
        $params['hash'] = hash_hmac('sha256', $dataCheckString, $secretKey);

        return http_build_query($params);
    }

    public function test_first_login_creates_a_user_and_a_linked_chat(): void
    {
        // См. комментарий в TelegramLoginTest — Registered без email иначе
        // упал бы без заглушки в User::sendEmailVerificationNotification().
        Notification::fake();

        $response = $this->post(route('auth.telegram.webapp'), ['init_data' => $this->signedInitData()]);

        $response->assertRedirect(config('fortify.home'));

        $user = User::query()->whereHas('telegramChat', fn ($q) => $q->where('chat_id', 987654321))->sole();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email);
        $this->assertNull($user->password);
        $this->assertSame('Иван Петров', $user->name);
        $this->assertNotNull($user->profile);

        Notification::assertNothingSent();
    }

    public function test_repeat_login_reuses_the_same_user(): void
    {
        $existing = User::factory()->telegram()->create();
        TelegramChat::create([
            'chat_id' => '987654321',
            'telegraph_bot_id' => $this->bot->id,
            'user_id' => $existing->id,
        ]);

        $this->post(route('auth.telegram.webapp'), ['init_data' => $this->signedInitData()]);

        $this->assertAuthenticatedAs($existing);
        $this->assertSame(1, User::query()->count());
    }

    public function test_tampered_hash_is_rejected(): void
    {
        // 'Иван' в user не подошёл бы напрямую: http_build_query() кодирует
        // кириллицу, а str_replace искал бы буквальную строку. auth_date не
        // кодируется — сдвигаем его на секунду, оставаясь в пределах TTL,
        // чтобы упасть именно на несовпадении hash, а не на протухшей дате.
        $initData = $this->signedInitData();
        $initData = preg_replace_callback('/auth_date=(\d+)/', fn ($m) => 'auth_date='.((int) $m[1] + 1), $initData);

        $response = $this->from(route('login'))
            ->post(route('auth.telegram.webapp'), ['init_data' => $initData]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_payload_signed_with_a_foreign_token_is_rejected(): void
    {
        $initData = $this->signedInitData(token: 'someone-elses-token');

        $this->post(route('auth.telegram.webapp'), ['init_data' => $initData]);

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_stale_auth_date_is_rejected(): void
    {
        $initData = $this->signedInitData(['auth_date' => (string) now()->subDays(2)->timestamp]);

        $this->post(route('auth.telegram.webapp'), ['init_data' => $initData]);

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_empty_init_data_is_rejected(): void
    {
        $response = $this->post(route('auth.telegram.webapp'), ['init_data' => '']);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_missing_bot_is_rejected(): void
    {
        $initData = $this->signedInitData();

        TelegramBot::query()->delete();
        TelegramBot::flushCache();

        $this->post(route('auth.telegram.webapp'), ['init_data' => $initData]);

        $this->assertGuest();
    }
}
