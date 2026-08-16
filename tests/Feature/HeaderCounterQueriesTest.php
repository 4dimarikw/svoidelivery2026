<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\User;
use Domain\Favorite\Models\Favorite;
use Domain\Order\Models\Order;
use Domain\Profile\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Шапка/аккаунт-панель (user-menu, mobile-nav, account-nav) раньше
 * дублировали SELECT'ы по favorites/addresses на каждый рендер — теперь
 * значения идут через FavoriteManager (мемоизирован на HTTP-запрос) и
 * User::addressesCount()/ordersCount() (мемоизированы на инстанс модели).
 * Тест фиксирует итоговое число запросов, а не только "не сломалось".
 */
class HeaderCounterQueriesTest extends TestCase
{
    use RefreshDatabase;

    private function countTableQueries(array $log, string $table): int
    {
        return count(array_filter(
            $log,
            fn (array $q): bool => (bool) preg_match('/from `'.$table.'`/', $q['query'])
        ));
    }

    public function test_home_page_queries_favorites_and_addresses_once_each(): void
    {
        $user = User::factory()->create();
        Favorite::factory()->count(3)->create(['user_id' => $user->id]);
        Address::factory()->count(2)->create(['user_id' => $user->id]);
        Order::factory()->count(4)->create(['user_id' => $user->id]);

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('home'))->assertOk();
        $log = DB::getQueryLog();

        $this->assertSame(1, $this->countTableQueries($log, 'favorites'));
        $this->assertSame(1, $this->countTableQueries($log, 'addresses'));
        // 'home' не рендерит account-nav — только user-menu; счётчик заказов
        // всё равно должен запрашиваться (и ровно один раз), т.к. user-menu
        // теперь тоже его показывает.
        $this->assertSame(1, $this->countTableQueries($log, 'orders'));
    }

    public function test_account_profile_page_queries_favorites_and_addresses_once_each(): void
    {
        // /account/profile рендерит и <x-ui.user-menu> (через header), и
        // <x-ui.account-nav> — до фикса это удваивало оба счётчика. Заказы —
        // тот же риск: у обоих меню теперь есть пункт "Мои заказы" со счётчиком.
        $user = User::factory()->create();
        Favorite::factory()->count(3)->create(['user_id' => $user->id]);
        Address::factory()->count(2)->create(['user_id' => $user->id]);
        Order::factory()->count(4)->create(['user_id' => $user->id]);

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('account.profile.edit'))->assertOk();
        $log = DB::getQueryLog();

        $this->assertSame(1, $this->countTableQueries($log, 'favorites'));
        $this->assertSame(1, $this->countTableQueries($log, 'addresses'));
        $this->assertSame(1, $this->countTableQueries($log, 'orders'));
    }

    public function test_guest_home_page_does_not_query_favorites_or_addresses(): void
    {
        DB::enableQueryLog();
        $this->get(route('home'))->assertOk();
        $log = DB::getQueryLog();

        $this->assertSame(0, $this->countTableQueries($log, 'favorites'));
        $this->assertSame(0, $this->countTableQueries($log, 'addresses'));
        $this->assertSame(0, $this->countTableQueries($log, 'orders'));
    }

    public function test_counters_render_the_correct_numbers(): void
    {
        $user = User::factory()->create();
        Favorite::factory()->count(3)->create(['user_id' => $user->id]);
        Address::factory()->count(2)->create(['user_id' => $user->id]);
        Order::factory()->count(4)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('account.profile.edit'));

        $response->assertOk();
        // Сид Alpine-стора (user-menu.blade.php:20).
        $response->assertSee('$store.favorites.count = 3', false);
        // Серверные бейджи адресов/избранного/заказов — рендерятся дважды (в
        // user-menu и в account-nav), но с одним и тем же корректным числом.
        $response->assertSee('<span class="font-mono text-micro">2</span>', false);
        $response->assertSee('<span class="font-mono text-micro">4</span>', false);
        $response->assertSee('<span class="font-mono text-micro" x-text="$store.favorites.count">3</span>', false);
    }
}
