<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use Domain\Auth\Models\User;
use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\Product;
use Domain\Favorite\Models\Favorite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoritesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('account.favorites.index'))->assertRedirect(route('login'));
    }

    public function test_toggle_adds_then_removes_a_favorite(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post(route('account.favorites.toggle', $product));
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'product_id' => $product->id]);

        $this->actingAs($user)->post(route('account.favorites.toggle', $product));
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_toggle_twice_in_a_row_never_duplicates_the_row(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        // Каждый вызов toggle() — отдельный HTTP-запрос: has()/add() внутри
        // FavoriteManager не переживают между запросами, поэтому дубль
        // проверяем через реальный unique-индекс, а не мемоизацию в памяти.
        $this->actingAs($user)->post(route('account.favorites.toggle', $product));
        $this->actingAs($user)->post(route('account.favorites.toggle', $product));
        $this->actingAs($user)->post(route('account.favorites.toggle', $product));

        $this->assertSame(1, Favorite::query()->where('user_id', $user->id)->where('product_id', $product->id)->count());
    }

    public function test_toggle_json_response_reports_state_and_count(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->postJson(route('account.favorites.toggle', $product));

        $response->assertOk();
        $response->assertJson(['favorited' => true, 'count' => 1]);
    }

    public function test_index_lists_only_the_current_users_favorites(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = Product::factory()->create();
        $theirs = Product::factory()->create();

        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $mine->id]);
        Favorite::factory()->create(['user_id' => $other->id, 'product_id' => $theirs->id]);

        $response = $this->actingAs($user)->get(route('account.favorites.index'));

        $response->assertOk();
        $response->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$mine->id]);
    }

    public function test_index_hides_unpublished_products(): void
    {
        $user = User::factory()->create();
        $published = Product::factory()->create(['status' => ProductStatus::PUBLISHED]);
        $archived = Product::factory()->create(['status' => ProductStatus::ARCHIVED]);

        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $published->id]);
        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $archived->id]);

        $response = $this->actingAs($user)->get(route('account.favorites.index'));

        $response->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$published->id]);
    }

    /**
     * Регрессия: hover:text-rust побеждал :class по CSS-специфичности,
     * снятие с избранного на этой странице выглядело так, будто ничего не
     * произошло (см. favorite-toggle.blade.php). Здесь проверяем то, что
     * действительно приходит с сервера: hover — только в динамической
     * ветке, не в статичном class.
     */
    public function test_favorite_toggle_hover_class_lives_only_in_the_binding(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $response = $this->actingAs($user)->get(route('account.favorites.index'));

        $response->assertOk();
        $response->assertSee(':class="favorited ? \'text-rust\' : \'text-tan-500 hover:text-rust\'"', false);
        $response->assertDontSee('class="grid h-[34px] w-[34px] place-items-center drop-shadow-fav transition hover:text-rust"', false);
    }

    /**
     * Регрессия: карточка должна исчезать из сетки сразу при снятии с
     * избранного здесь (removeOnUnfavorite), в отличие от каталога — см.
     * соседний тест в CatalogControllerTest.
     */
    public function test_index_page_wires_up_card_removal_on_unfavorite(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $response = $this->actingAs($user)->get(route('account.favorites.index'));

        $response->assertOk();
        $response->assertSee('x-on:favorite:removed.window="if ($event.detail.productId === '.$product->id.') removed = true"', false);
    }

    /**
     * Регрессия: x-text без x-data/x-init выше по DOM Alpine вообще не
     * инициализирует (см. account-nav.blade.php) — счётчик молча замирал
     * на серверном числе навсегда.
     */
    public function test_favorites_counter_in_sidebar_is_alpine_initialized(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.favorites.index'));

        $response->assertOk();
        $response->assertSee('<span class="font-mono text-micro" x-data x-text="$store.favorites.count">', false);
    }

    public function test_destroy_clears_only_the_current_users_favorites(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Favorite::factory()->count(2)->create(['user_id' => $user->id]);
        Favorite::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->delete(route('account.favorites.destroy'));

        $response->assertRedirect(route('account.favorites.index'));
        $this->assertSame(0, Favorite::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, Favorite::query()->where('user_id', $other->id)->count());
    }
}
