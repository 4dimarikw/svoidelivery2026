<?php

namespace Tests\Feature\Auth;

use Domain\Auth\Models\User;
use Domain\Telegram\Models\TelegramBot;
use Domain\Telegram\Support\WebAppInitData;
use Domain\Telegram\Support\WebAppSessionExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TelegramAuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Notification::fake();
        TelegramBot::create(['token' => 'test-bot-token', 'name' => 'Test', 'username' => 'test_bot']);
        RateLimiter::clear('');
    }

    private function webapp(array $overrides = []): string
    {
        $params = array_merge(['auth_date' => (string) now()->timestamp, 'user' => json_encode(['id' => 123456, 'first_name' => 'Test'])], $overrides);
        ksort($params);
        $check = implode("\n", array_map(fn ($key, $value) => "$key=$value", array_keys($params), $params));
        $params['hash'] = hash_hmac('sha256', $check, hash_hmac('sha256', 'test-bot-token', 'WebAppData', true));

        return http_build_query($params);
    }

    private function widget(array $overrides = []): array
    {
        $params = array_merge(['auth_date' => now()->timestamp, 'id' => 123456, 'first_name' => 'Test'], $overrides);
        $check = collect($params)->map(fn ($value, $key) => "$key=$value")->sort()->join("\n");
        $params['hash'] = hash_hmac('sha256', $check, hash('sha256', 'test-bot-token', true));

        return $params;
    }

    public static function dateCases(): array
    {
        return [['widget', -61, false], ['widget', -60, true], ['widget', 30, true], ['widget', 31, false],
            ['webapp', -86401, false], ['webapp', -86400, true], ['webapp', 30, true], ['webapp', 31, false]];
    }

    #[DataProvider('dateCases')]
    public function test_signed_date_boundaries(string $source, int $offset, bool $accepted): void
    {
        $date = (string) (now()->timestamp + $offset);
        $response = $source === 'widget'
            ? $this->get(route('auth.telegram.callback', $this->widget(['auth_date' => $date])))
            : $this->post(route('auth.telegram.webapp'), ['init_data' => $this->webapp(['auth_date' => $date])]);
        if ($accepted) {
            $this->assertAuthenticated();
        } else {
            $this->assertGuest();
            $response->assertSessionHasErrors('email');
            $this->assertSame(0, User::count());
        }
    }

    public static function invalidUsers(): array
    {
        return array_map(fn ($user) => [json_encode($user)], [null, [], ['id' => 0], ['id' => -1], ['id' => true], ['id' => 1.5], ['id' => '123'], ['id' => []], ['id' => 1, 'first_name' => []], ['id' => 1, 'username' => 12]]);
    }

    #[DataProvider('invalidUsers')]
    public function test_signed_invalid_user_is_rejected(string $user): void
    {
        $this->post(route('auth.telegram.webapp'), ['init_data' => $this->webapp(['user' => $user])])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public static function malformedFields(): array
    {
        return [['id'], ['auth_date'], ['hash'], ['first_name'], ['extra']];
    }

    #[DataProvider('malformedFields')]
    public function test_widget_arrays_are_rejected_without_type_errors(string $field): void
    {
        $payload = $this->widget();
        $payload[$field] = ['bad'];
        $this->get(route('auth.telegram.callback', $payload))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public static function invalidIntegers(): array
    {
        return [['0'], ['-1'], ['1.5'], ['1e3'], ['999999999999999999999999'], ['+123']];
    }

    #[DataProvider('invalidIntegers')]
    public function test_signed_widget_invalid_id_is_rejected(string $id): void
    {
        $this->get(route('auth.telegram.callback', $this->widget(['id' => $id])))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_webapp_outer_array_and_ambiguous_query_are_rejected(): void
    {
        $this->post(route('auth.telegram.webapp'), ['init_data' => ['bad']])->assertSessionHasErrors('email');
        foreach (['&auth_date[]=1', '&extra[]=1', '&user=bad', '&bad.key=value'] as $suffix) {
            $this->assertNull(WebAppInitData::verify($this->webapp().$suffix, 'test-bot-token'));
        }
        $this->assertGuest();
    }

    public function test_repeated_exchange_survives_session_regeneration(): void
    {
        $data = $this->webapp();
        $this->post(route('auth.telegram.webapp'), ['init_data' => $data])->assertSessionHasNoErrors();
        $owner = session('telegram_exchange_owner');
        $this->post(route('auth.telegram.webapp'), ['init_data' => $data])->assertSessionHasNoErrors();
        $this->assertAuthenticated();
        $this->assertSame($owner, session('telegram_exchange_owner'));
        $this->assertSame(1, User::count());
    }

    public function test_same_signature_in_new_session_is_rejected_but_new_launch_works(): void
    {
        $data = $this->webapp();
        $this->post(route('auth.telegram.webapp'), ['init_data' => $data])->assertSessionHasNoErrors();
        Auth::logout();
        session()->invalidate();
        $this->post(route('auth.telegram.webapp'), ['init_data' => $data])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post(route('auth.telegram.webapp'), ['init_data' => $this->webapp(['query_id' => 'new-launch'])]);
        $this->assertAuthenticated();
        $this->assertSame(1, User::count());
    }

    public function test_existing_authenticated_session_does_not_depend_on_init_data_ttl(): void
    {
        $this->post(route('auth.telegram.webapp'), ['init_data' => $this->webapp()]);
        $user = Auth::user();
        $this->travel(25)->hours();
        $this->get(route('account.profile.edit'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_cache_failure_does_not_allow_untracked_exchange(): void
    {
        Cache::shouldReceive('store')->once()->andThrow(new \RuntimeException('cache unavailable'));
        $this->assertFalse(app(WebAppSessionExchange::class)->claim(
            WebAppInitData::verify($this->webapp(), 'test-bot-token'), 1, app('session.store'),
        ));
    }
}
