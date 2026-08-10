<?php

namespace Tests\Feature\Auth;

use Domain\Auth\Models\User;
use Domain\Telegram\Models\TelegramBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Settings\SiteSettings;
use Tests\TestCase;

/**
 * <x-ui.telegram-autologin> — скрытая форма, смонтированная в
 * components/layouts/app.blade.php (на всех страницах, не только /login).
 * Сама отправка проверяется в TelegramWebAppLoginTest (POST auth/telegram/webapp);
 * здесь — только условия рендера самой формы и её redirect_to.
 */
class TelegramAutologinTest extends TestCase
{
    use RefreshDatabase;

    private TelegramBot $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = TelegramBot::create([
            'token' => 'test-bot-token',
            'name' => 'Test Bot',
            'username' => 'svoi_test_bot',
        ]);
    }

    private function enableAutologin(): void
    {
        $settings = app(SiteSettings::class);
        $settings->telegram_autologin = true;
        $settings->save();
    }

    public function test_form_is_absent_when_the_setting_is_disabled(): void
    {
        // telegram_autologin = false по умолчанию (settings-миграция).
        $this->get(route('login'))->assertDontSee('x-data="telegramAutologin"', false);
    }

    public function test_form_is_present_for_a_guest_when_enabled(): void
    {
        $this->enableAutologin();

        $response = $this->get(route('login'));

        $response->assertSee('x-data="telegramAutologin"', false);
        $response->assertSee(route('auth.telegram.webapp'), false);
    }

    public function test_redirect_to_matches_the_current_page(): void
    {
        $this->enableAutologin();

        $response = $this->get(route('about'));

        $response->assertSee('name="redirect_to" value="/about"', false);
    }

    public function test_form_is_absent_for_an_authenticated_user(): void
    {
        $this->enableAutologin();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertDontSee('x-data="telegramAutologin"', false);
    }

    public function test_form_is_absent_without_a_configured_bot(): void
    {
        $this->enableAutologin();

        TelegramBot::query()->delete();
        TelegramBot::flushCache();

        $this->get(route('login'))->assertDontSee('x-data="telegramAutologin"', false);
    }
}
