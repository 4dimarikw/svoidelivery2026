<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class RegistrationHoneypotTest extends TestCase
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

    public function test_filled_trap_field_is_rejected(): void
    {
        $response = $this->from(route('register'))
            ->post(route('register.store'), $this->validPayload(['website_url' => 'https://spam.example']));

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'ivan@example.com']);
    }

    public function test_submitting_faster_than_min_seconds_is_rejected(): void
    {
        $now = Carbon::now();
        Carbon::setTestNow($now);

        $response = $this->from(route('register'))->post(route('register.store'), $this->validPayload([
            'form_loaded_at' => Crypt::encryptString((string) $now->timestamp),
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'ivan@example.com']);

        Carbon::setTestNow();
    }

    public function test_submitting_after_min_seconds_passes(): void
    {
        $loadedAt = Carbon::now();
        Carbon::setTestNow($loadedAt);
        $token = Crypt::encryptString((string) $loadedAt->timestamp);

        // now() двигается вперёд — правило и компонент оба читают now(), не
        // time(), поэтому setTestNow тут работает без реального sleep().
        Carbon::setTestNow($loadedAt->copy()->addSeconds(5));

        $response = $this->post(route('register.store'), $this->validPayload(['form_loaded_at' => $token]));

        $this->assertDatabaseHas('users', ['email' => 'ivan@example.com']);
        $response->assertRedirect(route('account.profile.edit'));

        Carbon::setTestNow();
    }

    public function test_forged_plain_timestamp_is_rejected(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), $this->validPayload([
            // Голый unix-timestamp вместо Crypt::encryptString() — доказывает,
            // что подмена без ключа приложения не проходит.
            'form_loaded_at' => (string) now()->subMinutes(2)->timestamp,
        ]));

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'ivan@example.com']);
    }

    public function test_expired_timestamp_is_rejected_with_its_own_message(): void
    {
        $loadedAt = Carbon::now();
        Carbon::setTestNow($loadedAt);
        $token = Crypt::encryptString((string) $loadedAt->timestamp);

        Carbon::setTestNow($loadedAt->copy()->addSeconds(config('security.honeypot.max_seconds') + 1));

        $response = $this->from(route('register'))->post(route('register.store'), $this->validPayload([
            'form_loaded_at' => $token,
        ]));

        $response->assertSessionHasErrors(['email' => __('account.security.form_expired')]);

        Carbon::setTestNow();
    }

    public function test_missing_honeypot_fields_are_rejected(): void
    {
        $payload = $this->validPayload();
        unset($payload['website_url'], $payload['form_loaded_at']);

        $response = $this->from(route('register'))->post(route('register.store'), $payload);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'ivan@example.com']);
    }

    public function test_honeypot_can_be_disabled_by_config(): void
    {
        config(['security.honeypot.enabled' => false]);

        $payload = $this->validPayload();
        unset($payload['website_url'], $payload['form_loaded_at']);

        $this->post(route('register.store'), $payload);

        $this->assertDatabaseHas('users', ['email' => 'ivan@example.com']);
    }

    public function test_register_page_renders_the_trap_and_timer(): void
    {
        $response = $this->get(route('register'));

        $response->assertSee('name="website_url"', false);
        $response->assertSee('left:-9999px', false);
        $response->assertSee('name="form_loaded_at"', false);
    }

    public function test_honeypot_message_does_not_reveal_which_check_failed(): void
    {
        $this->post(route('register.store'), $this->validPayload(['website_url' => 'spam']));
        $trapMessage = (string) session('errors')->getBag('default')->first('email');

        $this->post(route('register.store'), $this->validPayload([
            'email' => 'other@example.com',
            'form_loaded_at' => Crypt::encryptString((string) now()->timestamp),
        ]));
        $tooFastMessage = (string) session('errors')->getBag('default')->first('email');

        $this->assertSame($trapMessage, $tooFastMessage);
        $this->assertSame(__('account.security.form_rejected'), $trapMessage);
    }
}
