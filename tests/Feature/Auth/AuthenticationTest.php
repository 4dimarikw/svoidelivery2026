<?php

namespace Tests\Feature\Auth;

use Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_valid_credentials_log_the_user_in(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('account.profile.edit'));
    }

    public function test_invalid_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unknown_email_is_rejected(): void
    {
        $response = $this->post(route('login.store'), [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $this->assertGuest();
    }

    public function test_logout_clears_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout');

        $this->assertGuest();
    }

    public function test_throttled_login_returns_the_translated_message_not_a_bare_429(): void
    {
        // FortifyServiceProvider задаёт кастомный ->response() на лимитере
        // 'login' — без него троттлинг отдавал бы голый Symfony 429 вместо
        // переведённой auth.throttle строки (см. CLAUDE.md).
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        RateLimiter::clear('login');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
        }

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong',
        ]);

        // Обычный (не троттлящий) неверный пароль тоже даёт 302 + ошибку на
        // email — различает их только текст сообщения, поэтому сравниваем
        // именно с переведённой auth.throttle строкой, а не только со статусом.
        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
        $message = (string) session('errors')->getBag('default')->first('email');
        $this->assertStringContainsString('Слишком много попыток входа', $message);
    }
}
