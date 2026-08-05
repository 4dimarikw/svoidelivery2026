<?php

namespace Tests\Feature\Auth;

use Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * GET /user/confirm-password регистрируется безусловно при config('fortify.views')
 * === true, и у Fortify нет дефолтного ConfirmPasswordViewResponse — без
 * Fortify::confirmPasswordView() в FortifyServiceProvider этот route 500-ит
 * (см. CLAUDE.md). Это регрессионный тест именно на тот риск.
 */
class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_renders_without_500(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();
    }

    public function test_correct_password_confirms(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->actingAs($user)->post(route('password.confirm.store'), [
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('account.profile.edit'));
    }

    public function test_incorrect_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $response = $this->actingAs($user)
            ->from(route('password.confirm'))
            ->post(route('password.confirm.store'), ['password' => 'wrong']);

        $response->assertSessionHasErrors('password');
    }
}
