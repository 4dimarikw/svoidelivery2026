<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Aligns `untappd_beers` (created before the catalog schema was designed)
     * with data/catalog-database-structure.md §4.8: a `beer_id` can be
     * registered from the CSV import before the rest of the Untappd data is
     * synced, so everything except `beer_id` must be nullable.
     */
    public function up(): void
    {
        Schema::table('untappd_beers', function (Blueprint $table) {
            $table->integer('rating_count')->default(0)->nullable(false)->change();
            $table->decimal('rating_score', 3, 2)->nullable()->change();
            $table->string('url', 2048)->nullable()->change();
            $table->timestamp('synced_at')->nullable()->after('url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('untappd_beers', function (Blueprint $table) {
            $table->dropColumn('synced_at');
            $table->string('url')->nullable(false)->change();
            $table->decimal('rating_score', 3, 2)->nullable(false)->change();
            $table->integer('rating_count')->change();
        });
    }
};
