<?php

use Domain\Cart\Models\Cart;
use Domain\Catalog\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Cart::class)
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignIdFor(Product::class)
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // decimal(12,2) в рублях — та же конвенция хранения, что у
            // products.price; PriceCast конвертирует рубли <-> минорные единицы
            // (копейки) в самом Support\ValueObjects\Price.
            $table->decimal('price', 12, 2)->default(0);

            $table->integer('quantity')
                ->default(1);

            $table->timestamps();

            $table->unique(['cart_id', 'product_id']);
            $table->index(['cart_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (! app()->isProduction()) {
            Schema::dropIfExists('cart_items');
        }
    }
};
