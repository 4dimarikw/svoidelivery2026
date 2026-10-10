<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Миграция меняет только схему: существующие адреса не обрезаем и не пересоздаём.
        if (DB::table('products')->whereRaw('CHAR_LENGTH(slug) > 200')->exists()) {
            throw new RuntimeException('Нельзя уменьшить products.slug: найдены slug длиннее 200 символов.');
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('slug', 200)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug', 255)->change();
        });
    }
};
