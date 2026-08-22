<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Проверяет саму проводку SmartCaptchaRule в CreateNewUser/register.blade.php —
 * поведение самого правила (fail-open и т.п.) уже покрыто
 * tests\Unit\Rules\SmartCaptchaRuleTest.
 */
class SmartCaptchaRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Иван Иванов',
            'email' => 'ivan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'age_confirmed' => '1',
            'terms_accepted' => '1',
            'website_url' => '',
            'form_loaded_at' => Crypt::encryptString((string) now()->subMinutes(2)->timestamp),
        ], $overrides);
    }

    public function test_widget_is_not_rendered_when_disabled(): void
    {
        config(['security.smart_captcha.enabled' => false]);

        $response = $this->get(route('register'));

        $response->assertDontSee('smart-captcha', false);
        $response->assertDontSee('smartcaptcha.cloud.yandex.ru', false);
    }

    public function test_registration_succeeds_with_a_valid_token(): void
    {
        config(['security.smart_captcha.enabled' => true, 'security.smart_captcha.server_key' => 'k']);
        Http::fake(['smartcaptcha.cloud.yandex.ru/*' => Http::response(['status' => 'ok'])]);

        $this->post(route('register.store'), $this->validPayload(['smart-token' => 'good-token']));

        $this->assertDatabaseHas('users', ['email' => 'ivan@example.com']);
    }

    public function test_registration_fails_without_a_token(): void
    {
        config(['security.smart_captcha.enabled' => true, 'security.smart_captcha.server_key' => 'k']);
        Http::fake();

        $response = $this->from(route('register'))->post(route('register.store'), $this->validPayload());

        $response->assertSessionHasErrors('smart-token');
        $this->assertDatabaseMissing('users', ['email' => 'ivan@example.com']);
        Http::assertNothingSent();
    }
}
