<?php

namespace Tests\Feature\Account;

use Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('account.profile.edit'))->assertRedirect(route('login'));
    }

    public function test_page_renders_when_profile_is_null(): void
    {
        $user = User::factory()->create();

        // Модель специально не имеет автосозданного профиля (обходим листенер).
        $this->assertNull($user->profile);

        $this->actingAs($user)->get(route('account.profile.edit'))->assertOk();
    }

    public function test_update_creates_profile_when_missing_then_updates_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
        ]);

        $this->assertSame('Иван', $user->profile()->first()->first_name);

        $this->actingAs($user)->put(route('account.profile.update'), [
            'first_name' => 'Пётр',
            'last_name' => 'Петров',
        ]);

        $this->assertSame(1, $user->profile()->count());
        $this->assertSame('Пётр', $user->fresh()->profile->first_name);
    }

    public function test_social_vk_must_be_a_valid_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('account.profile.edit'))
            ->put(route('account.profile.update'), ['social_vk' => 'not a url']);

        $response->assertSessionHasErrors('social_vk');
    }

    public function test_update_stores_social_links_as_json_and_omits_blank_ones(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'social_telegram' => 'https://t.me/ivan',
            'social_max' => 'https://max.ru/u/ivan',
            'social_vk' => '',
        ]);

        $profile = $user->fresh()->profile;

        $this->assertSame('https://t.me/ivan', $profile->socialLink('telegram'));
        $this->assertSame('https://max.ru/u/ivan', $profile->socialLink('max'));
        $this->assertNull($profile->socialLink('vk'));
    }

    public function test_ajax_update_returns_blank_200_without_x_redirect(): void
    {
        // resources/js/ui.js: без заголовка X-Redirect uiForm.submit() не
        // навигирует — только явный header означает редирект. Раньше
        // response.url совпадал с PUT-only экшеном формы, и переход по нему
        // GET'ом давал 405 (см. CLAUDE.md, "Private account area").
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson(route('account.profile.update'), ['first_name' => 'Иван']);

        $response->assertOk();
        $response->assertHeaderMissing('X-Redirect');
        $response->assertExactJson([]);
    }

    public function test_no_js_update_redirects_back_with_status(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('account.profile.update'), ['first_name' => 'Иван']);

        $response->assertRedirect();
        $this->assertSame('account-profile-updated', session('status'));
    }

    public function test_fortify_profile_information_errors_use_their_own_bag(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('account.profile.edit'))
            ->put(route('user-profile-information.update'), [
                'name' => 'Иван',
                'email' => $other->email,
            ]);

        $response->assertSessionHasErrors('email', null, 'updateProfileInformation');
    }

    public function test_fortify_password_errors_use_their_own_bag(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $response = $this->actingAs($user)
            ->from(route('account.profile.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertSessionHasErrors('current_password', null, 'updatePassword');
    }
}
