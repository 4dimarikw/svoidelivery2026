<?php

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
        Schema::create('beer_product_details', function (Blueprint $table) {
            $table->foreignId('product_id')->primary()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('beer_style_id')->nullable()->constrained('beer_styles')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('untappd_beer_id')->nullable()->constrained('untappd_beers')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('abv', 5, 2)->nullable();
            $table->decimal('ibu', 6, 2)->nullable();
            $table->decimal('plato', 5, 2)->nullable();
            $table->decimal('ebc', 6, 2)->nullable();
            $table->timestamps();

            $table->index(['beer_style_id', 'abv', 'product_id'], 'beer_details_style_abv_idx');
            $table->index(['abv', 'product_id'], 'beer_details_abv_idx');
            $table->index(['ibu', 'product_id'], 'beer_details_ibu_idx');
            $table->index(['plato', 'product_id'], 'beer_details_plato_idx');
            $table->index(['ebc', 'product_id'], 'beer_details_ebc_idx');
            $table->index(['untappd_beer_id', 'product_id'], 'beer_details_untappd_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beer_product_details');
    }
};
