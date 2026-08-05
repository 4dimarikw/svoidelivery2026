<?php

namespace Tests\Feature\Auth;

use Domain\Auth\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_renders(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_requesting_a_link_for_a_known_email_sends_a_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_screen_renders_with_the_token(): void
    {
        $this->get(route('password.reset', ['token' => 'sometoken']))->assertOk();
    }

    public function test_valid_token_resets_the_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        // PasswordResetResponse (не LoginResponse) — редирект на route('login')
        // со session('status'), а не на config('fortify.home').
        $response->assertRedirect(route('login'));
        $this->assertNotNull(session('status'));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->from(route('password.reset', ['token' => 'bad']))->post(route('password.update'), [
            'token' => 'definitely-not-the-real-token',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }
}
