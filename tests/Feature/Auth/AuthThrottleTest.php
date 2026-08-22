<?php

namespace Tests\Feature\Auth;

use Domain\Auth\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * App\Http\Middleware\ThrottleAuthForms (register/password-reset) +
 * RateLimiter::for('verification') в FortifyServiceProvider. CACHE_STORE=array
 * в phpunit.xml — лимитер стартует пустым в каждом методе, ручной очистки
 * не требуется, но состояние сохраняется МЕЖДУ запросами внутри одного
 * метода, что тестам ниже и нужно.
 */
class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $overrides = []): array
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

    public function test_register_is_throttled_per_email(): void
    {
        config(['security.register_throttle.per_identity' => 1]);

        $this->post(route('register.store'), $this->registerPayload());

        // Второй запрос намеренно тоже без согласий — если бы троттл не
        // сработал первым, ошибка легла бы на age_confirmed/terms_accepted.
        $response = $this->from(route('register'))
            ->post(route('register.store'), $this->registerPayload(['age_confirmed' => false, 'terms_accepted' => false]));

        $response->assertSessionHasErrors('email');
        $response->assertSessionDoesntHaveErrors(['age_confirmed', 'terms_accepted']);
    }

    public function test_register_is_throttled_per_ip_across_different_emails(): void
    {
        config(['security.register_throttle.per_ip' => 1]);

        $this->post(route('register.store'), $this->registerPayload(['email' => 'first@example.com']));

        $response = $this->from(route('register'))
            ->post(route('register.store'), $this->registerPayload(['email' => 'second@example.com']));

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    public function test_forgot_password_is_throttled(): void
    {
        Notification::fake();
        config(['security.password_reset_throttle.per_identity' => 1]);

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);
        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_reset_password_is_throttled(): void
    {
        config(['security.password_reset_throttle.per_identity' => 1]);

        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => 'wrong-token-1',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response = $this->from(route('password.reset', ['token' => 'wrong-token-2']))
            ->post(route('password.update'), [
                'token' => 'wrong-token-2',
                'email' => $user->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertSessionHasErrors('email');
        $message = (string) session('errors')->getBag('default')->first('email');
        $this->assertStringContainsString('Слишком много запросов', $message);
    }

    public function test_verification_resend_is_throttled(): void
    {
        Notification::fake();
        config(['security.verification_throttle.per_user' => 1]);

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'));
        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertRedirect();
        $this->assertSame('verification-throttled', session('status'));
    }

    public function test_confirm_password_is_throttled(): void
    {
        config(['security.password_confirm_throttle.per_user' => 1]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'wrong-1']);

        $response = $this->actingAs($user)
            ->from(route('password.confirm'))
            ->post(route('password.confirm.store'), ['password' => 'wrong-2']);

        $response->assertSessionHasErrors('password');
        $message = (string) session('errors')->getBag('default')->first('password');
        $this->assertStringContainsString('Слишком много запросов', $message);
    }

    public function test_get_register_page_is_never_throttled(): void
    {
        config(['security.register_throttle.per_ip' => 1]);

        for ($i = 0; $i < 5; $i++) {
            $this->get(route('register'))->assertOk();
        }
    }

    public function test_logout_is_not_throttled(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect();
        $this->assertGuest();
    }
}
