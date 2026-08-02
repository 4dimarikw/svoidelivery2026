<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds `description` to `untappd_beers` (data/catalog-database-structure.md §4.8):
     * Untappd's `beer_description` is now persisted so it's available on
     * cache-hit reads, not only on a fresh API call.
     */
    public function up(): void
    {
        Schema::table('untappd_beers', function (Blueprint $table) {
            $table->text('description')->nullable()->after('style');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('untappd_beers', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
