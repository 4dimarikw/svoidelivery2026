<?php

use Domain\Catalog\Enums\ProductStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('external_code', 64)->unique();
            $table->string('article')->nullable();
            $table->string('name', 512);
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('volume_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('container_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('in_stock')->default(false);
            $table->unsignedSmallInteger('package_units')->nullable();
            $table->string('packaging_raw')->nullable();
            $table->string('source_category_path', 512)->nullable();
            $table->unsignedSmallInteger('shelf_life_days')->nullable();
            $table->string('brand')->nullable();
            $table->string('status', 32)->default(ProductStatus::DRAFT->value);
            $table->json('flags')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'manufacturer_id', 'price'], 'products_category_manufacturer_price_idx');
            $table->index(['category_id', 'price'], 'products_category_price_idx');
            $table->index(['manufacturer_id', 'price'], 'products_manufacturer_price_idx');
            $table->index('price', 'products_price_idx');
            $table->index(['volume_id', 'container_id'], 'products_volume_container_idx');
            $table->index('container_id', 'products_container_idx');
            $table->index(['status', 'in_stock', 'id'], 'products_status_stock_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
