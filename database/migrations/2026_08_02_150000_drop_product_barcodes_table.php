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
        Schema::dropIfExists('product_barcodes');
    }

    /**
     * Reverse the migrations.
     *
     * Точная копия исходной create_product_barcodes_table миграции — данные,
     * разумеется, не восстанавливаются.
     */
    public function down(): void
    {
        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('barcode', 32)->unique();
            $table->timestamps();

            $table->index('product_id');
        });
    }
};
