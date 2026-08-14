<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * expects_container/expects_volume/price_exempt/name_from_article/
     * default_brand/container_code — behavioural flags for the CatalogImport
     * pipeline, formerly the per-category keys in
     * config('catalog_import.categories') (`container`, `volume`,
     * `price_exempt`, `default_brand`, `name_from_article`,
     * `container_code`). Prefixed `expects_*` so `expects_container` isn't
     * confused with `products.container_id`.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('expects_container')->default(false);
            $table->boolean('expects_volume')->default(false);
            $table->boolean('price_exempt')->default(false);
            $table->boolean('name_from_article')->default(false);
            $table->string('default_brand')->nullable();
            // Free-text tare code, matched by ResolveContainerStage against
            // containers.code — no FK, same as the config value was a bare string.
            $table->string('container_code', 64)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
