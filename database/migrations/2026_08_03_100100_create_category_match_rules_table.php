<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the `match[]` arrays from the old config/catalog_import.php
     * category registry — one row per rule, interpreted by
     * CategorySlugResolver in `priority` order (formerly "order of
     * declaration in the config array").
     *
     * `type` — alcohol | contains | accessory_title (see CategorySlugResolver).
     * `match_when` — advent | no_abv | style | default; only meaningful for
     * type=alcohol. Named `match_when` because `when` is a MySQL reserved word.
     * `value` — the type-specific payload: `keyword` for alcohol/style,
     * `needle` for contains, one word for accessory_title (the old config's
     * `keywords[]` array is flattened to one row per word — the resolver
     * already collapses them into a single flat keyword=>slug map, see
     * CategorySlugResolver::resolveAccessoryTitleSlug()).
     */
    public function up(): void
    {
        Schema::create('category_match_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('match_when', 32)->nullable();
            $table->string('value')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_match_rules');
    }
};
