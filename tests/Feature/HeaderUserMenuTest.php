<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderUserMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_header_shows_login_and_register_only(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('login'), false);
        $response->assertSee(route('register'), false);
        $response->assertDontSee(route('account.profile.edit'), false);
    }

    public function test_authenticated_header_shows_cart_icon_and_user_menu(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee($user->name);
        $response->assertSee(route('cart.index'), false);
        $response->assertSee(route('account.profile.edit'), false);
        $response->assertSee(route('account.addresses.index'), false);
        $response->assertSee(route('account.favorites.index'), false);
        $response->assertSee(route('logout'), false);
    }

    public function test_authenticated_header_no_longer_has_a_standalone_favorites_icon(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        // Убрана отдельная иконка-ссылка избранного в шапке (осталась только
        // как пункт выпадающего меню, см. user-menu.blade.php) — её
        // aria-label больше не должен встречаться (текст "Избранное" сам по
        // себе так же встречается в пункте меню — по нему не отличить).
        $response->assertDontSee('aria-label="'.__('layout.nav.favorites').'"', false);
    }
}
