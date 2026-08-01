<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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

            $table->integer('rating_count');

            $table->decimal('rating_score', 3, 2);

            $table->string('label')->nullable();

            $table->string('url');

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
