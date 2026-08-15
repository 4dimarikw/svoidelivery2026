<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\User;
use Domain\Cart\Models\Cart;
use Domain\Cart\Models\CartItem;
use Domain\Profile\Models\Address;
use Domain\Profile\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Фиксирует число запросов к таблицам, которые раньше читались дважды за
 * рендер (аудит вьюх): cart_items/profiles/addresses на /cart (оформление
 * встроено туда же, отдельной страницы /checkout больше нет), addresses на
 * /account/addresses. Контроллеры переиспользуют уже прогретые
 * CartManager/коллекции вместо повторного чтения тех же строк.
 */
class DuplicateQueryAuditTest extends TestCase
{
    use RefreshDatabase;

    private function countTableQueries(array $log, string $table): int
    {
        return count(array_filter(
            $log,
            fn (array $q): bool => (bool) preg_match('/from `'.$table.'`/', $q['query'])
        ));
    }

    public function test_cart_page_queries_cart_items_profiles_and_addresses_once_each(): void
    {
        $user = User::factory()->create();
        Profile::factory()->create(['user_id' => $user->id]);
        Address::factory()->count(2)->create(['user_id' => $user->id]);
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        // Без явного product_id — CartItemFactory резолвит ProductFactory
        // отдельно на каждую строку (unique(['cart_id', 'product_id']) на
        // cart_items не пускает две строки на один товар).
        CartItem::factory()->count(2)->create(['cart_id' => $cart->id]);

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('cart.index'))->assertOk();
        $log = DB::getQueryLog();

        $this->assertSame(1, $this->countTableQueries($log, 'cart_items'));
        $this->assertSame(1, $this->countTableQueries($log, 'user_profiles'));
        $this->assertSame(1, $this->countTableQueries($log, 'addresses'));
    }

    public function test_addresses_index_page_queries_addresses_once(): void
    {
        $user = User::factory()->create();
        Address::factory()->count(2)->create(['user_id' => $user->id]);

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('account.addresses.index'))->assertOk();
        $log = DB::getQueryLog();

        $this->assertSame(1, $this->countTableQueries($log, 'addresses'));
    }
}
