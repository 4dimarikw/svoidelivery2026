<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Behavioural flags for the CatalogImport pipeline, formerly the
     * per-category keys in config('catalog_import.categories') (`container`,
     * `volume`, `price_exempt`, `default_brand`, `name_from_article`,
     * `container_code` — see the old config header for their meaning).
     * Prefixed `expects_*` so `expects_container` isn't confused with
     * `products.container_id`.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('expects_container')->default(false)->after('is_active');
            $table->boolean('expects_volume')->default(false)->after('expects_container');
            $table->boolean('price_exempt')->default(false)->after('expects_volume');
            $table->boolean('name_from_article')->default(false)->after('price_exempt');
            $table->string('default_brand')->nullable()->after('name_from_article');
            // Free-text tare code, matched by ResolveContainerStage against
            // containers.code — no FK, same as the config value was a bare string.
            $table->string('container_code', 64)->nullable()->after('default_brand');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'expects_container',
                'expects_volume',
                'price_exempt',
                'name_from_article',
                'default_brand',
                'container_code',
            ]);
        });
    }
};
