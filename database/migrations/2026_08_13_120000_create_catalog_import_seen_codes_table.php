<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Рабочая область одного прогона catalog:import — коды товаров
     * (products.external_code), реально встреченные в текущем CSV.
     * Заполняется Services\CatalogImport\SeenCodeCollector построчно, до
     * пайплайна стейджей, поэтому сюда попадают и отбракованные строки
     * (NormalizeRowStage и т.п.) — присутствие в файле, а не факт
     * успешного импорта, см. src/Domain/Catalog/Actions/ZeroOutStaleProductsAction.
     * Очищается в начале импорта (SeenCodeCollector::reset) и после
     * успешного обнуления (ZeroOutStaleProductsAction) — не история, снапшот.
     */
    public function up(): void
    {
        Schema::create('catalog_import_seen_codes', function (Blueprint $table) {
            $table->string('external_code', 64)->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_import_seen_codes');
    }
};
