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
        Schema::create('untappd_beers', function (Blueprint $table) {
            $table->id();

            $table->integer('beer_id')->unique();

            $table->string('name')->nullable();

            $table->string('brewery')->nullable();

            $table->string('style')->nullable();

            $table->text('description')->nullable();

            $table->integer('rating_count')->default(0);

            $table->decimal('rating_score', 3, 2)->nullable();

            $table->string('label')->nullable();

            $table->string('url', 2048)->nullable();

            $table->timestamp('synced_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('untappd_beers');
    }
};
