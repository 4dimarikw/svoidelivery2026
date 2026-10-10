<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Не допускаем обрезку данных при применении миграции в другом окружении.
        if (DB::table('products')->whereRaw('CHAR_LENGTH(name) > 255')->exists()) {
            throw new RuntimeException('Нельзя уменьшить products.name: найдены названия длиннее 255 символов.');
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('name', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('name', 512)->change();
        });
    }
};
