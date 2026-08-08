<?php

use Domain\Catalog\Models\Product;
use Domain\Order\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Order::class)
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Плоский каталог (products), вариаций товара в проекте нет —
            // FK на products, не на product_variations.
            $table->foreignIdFor(Product::class)
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // decimal(12,2) — рубли, как products.price/cart_items.price;
            // OrderItem::price кастуется PriceCast.
            $table->decimal('price', 12, 2)
                ->index();

            $table->unsignedInteger('quantity')
                ->default(1);

            $table->timestamps();

            $table->index(['order_id', 'product_id']);
            $table->index(['created_at', 'order_id']);
        });
    }

    public function down(): void
    {
        if (! app()->isProduction()) {
            Schema::dropIfExists('order_items');
        }
    }
};
