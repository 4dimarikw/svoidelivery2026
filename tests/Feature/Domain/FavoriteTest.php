<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use Domain\Auth\Models\User;
use Domain\Catalog\Models\Product;
use Domain\Favorite\FavoriteManager;
use Domain\Favorite\Models\Favorite;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_has_no_favorites_and_manager_is_a_no_op(): void
    {
        $manager = app(FavoriteManager::class);
        $product = Product::factory()->create();

        $this->assertSame(0, $manager->count());
        $this->assertFalse($manager->has($product));

        $manager->add($product);

        $this->assertDatabaseMissing('favorites', ['product_id' => $product->id]);
    }

    public function test_add_creates_a_favorite_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($user);

        $manager = app(FavoriteManager::class);
        $manager->add($product);
        $manager->add($product);

        $this->assertSame(1, Favorite::query()->where('user_id', $user->id)->count());
        $this->assertTrue($manager->has($product));
    }

    public function test_remove_deletes_the_favorite(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($user);

        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        app(FavoriteManager::class)->remove($product);

        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_toggle_switches_state_and_returns_it(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($user);

        $manager = app(FavoriteManager::class);

        $this->assertTrue($manager->toggle($product));
        $this->assertTrue($manager->has($product));

        $this->assertFalse($manager->toggle($product));
        $this->assertFalse($manager->has($product));
    }

    public function test_truncate_removes_only_the_current_users_favorites(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Favorite::factory()->count(2)->create(['user_id' => $user->id]);
        Favorite::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user);
        app(FavoriteManager::class)->truncate();

        $this->assertSame(0, Favorite::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, Favorite::query()->where('user_id', $other->id)->count());
    }

    public function test_duplicate_favorite_violates_the_unique_index(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->expectException(QueryException::class);
        Favorite::query()->create(['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_deleting_the_user_cascades_to_their_favorites(): void
    {
        $user = User::factory()->create();
        Favorite::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id]);
    }

    public function test_deleting_the_product_cascades_to_favorites(): void
    {
        $product = Product::factory()->create();
        $favorite = Favorite::factory()->create(['product_id' => $product->id]);

        $product->delete();

        $this->assertDatabaseMissing('favorites', ['id' => $favorite->id]);
    }
}
