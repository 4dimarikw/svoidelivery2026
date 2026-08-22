<?php

namespace Tests\Feature\Auth;

use Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class RegistrationTest extends TestCase
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
            // Honeypot включён по умолчанию (config/security.php) — без
            // этих двух полей Infrastructure\Rules\HoneypotRule отклонял бы
            // каждую отправку ниже как бота. Таймер "заряжен" в прошлое, а
            // не в момент вызова — min_seconds уже позади.
            'website_url' => '',
            'form_loaded_at' => Crypt::encryptString((string) now()->subMinutes(2)->timestamp),
        ], $overrides);
    }

    public function test_registration_screen_renders(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_valid_registration_creates_a_user(): void
    {
        $this->post(route('register.store'), $this->validPayload());

        $this->assertDatabaseHas('users', ['email' => 'ivan@example.com']);
    }

    public function test_registered_event_provisions_a_profile(): void
    {
        $this->post(route('register.store'), $this->validPayload());

        $user = User::query()->firstWhere('email', 'ivan@example.com');

        $this->assertNotNull($user->profile);
    }

    public function test_consent_fields_are_not_persisted_on_the_user(): void
    {
        $this->post(route('register.store'), $this->validPayload());

        $user = User::query()->firstWhere('email', 'ivan@example.com');

        $this->assertArrayNotHasKey('age_confirmed', $user->getAttributes());
        $this->assertArrayNotHasKey('terms_accepted', $user->getAttributes());
    }

    public function test_missing_age_confirmation_fails_with_its_own_message(): void
    {
        $response = $this->from(route('register'))
            ->post(route('register.store'), $this->validPayload(['age_confirmed' => false]));

        $response->assertSessionHasErrors(['age_confirmed' => __('account.register.age_gate_required')]);
        $this->assertDatabaseMissing('users', ['email' => 'ivan@example.com']);
    }

    public function test_missing_terms_acceptance_fails_with_its_own_message(): void
    {
        $response = $this->from(route('register'))
            ->post(route('register.store'), $this->validPayload(['terms_accepted' => false]));

        $response->assertSessionHasErrors(['terms_accepted' => __('account.register.terms_gate_required')]);
    }

    public function test_duplicate_email_fails_validation(): void
    {
        User::factory()->create(['email' => 'ivan@example.com']);

        $response = $this->from(route('register'))
            ->post(route('register.store'), $this->validPayload());

        $response->assertSessionHasErrors('email');
    }

    public function test_registration_redirects_to_fortify_home_regardless_of_verification(): void
    {
        // Laravel\Fortify\Http\Responses\RegisterResponse редиректит на
        // config('fortify.home') безусловно — верификация e-mail тут ни при
        // чём, она перехватывает только СЛЕДУЮЩИЙ запрос через middleware('verified').
        $response = $this->post(route('register.store'), $this->validPayload());

        $response->assertRedirect(route('account.profile.edit'));
    }

    public function test_unverified_user_is_redirected_away_from_the_account_area(): void
    {
        // Это тот самый эффект middleware(['auth', 'verified']) на /account
        // (routes/web.php) + Features::emailVerification() (config/fortify.php) —
        // CLAUDE.md называет верификацию "deliberately deferred", что здесь не так.
        $this->post(route('register.store'), $this->validPayload());

        $response = $this->get(route('account.profile.edit'));

        $response->assertRedirect(route('verification.notice'));
    }
}
