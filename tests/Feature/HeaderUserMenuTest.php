<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderUserMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_header_shows_login_on_desktop_only(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);

        // Проверяем именно верхнюю шапку: нижняя навигация тоже содержит вход.
        $loginLinks = $xpath->query('//header//a[@href="'.route('login').'"]');
        $this->assertCount(1, $loginLinks);
        $login = $loginLinks->item(0);
        $this->assertSame(__('account.login.submit'), $login->getAttribute('aria-label'));
        $this->assertSame('', trim($login->textContent));
        $this->assertCount(1, $xpath->query('.//*[local-name()="svg"]', $login));
        $classes = preg_split('/\s+/', trim($login->getAttribute('class')));
        $this->assertContains('hidden', $classes);
        $this->assertContains('md:inline-flex', $classes);
        $this->assertCount(0, $xpath->query('//header//a[@href="'.route('register').'"]'));
        $this->assertCount(0, $xpath->query('//header//a[@href="'.route('account.profile.edit').'"]'));
    }

    public function test_authenticated_header_shows_cart_icon_and_user_menu(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee($user->name);
        $response->assertSee(route('cart.index'), false);
        $response->assertSee(route('account.profile.edit'), false);
        $response->assertSee(route('account.orders.index'), false);
        $response->assertSee(route('account.addresses.index'), false);
        $response->assertSee(route('account.favorites.index'), false);
        $response->assertSee(route('logout'), false);
    }

    public function test_authenticated_header_no_longer_has_a_standalone_favorites_icon(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        // Избранное доступно через пункт выпадающего меню user-menu; отдельная иконка в шапке не выводится.
        $response->assertDontSee('aria-label="'.__('layout.nav.favorites').'"', false);
    }
}
