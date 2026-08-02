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
        Schema::table('products', function (Blueprint $table) {
            // Не используются бизнес-логикой проекта. source_uuid имел unique() —
            // MySQL сам снимает индекс, привязанный только к удаляемой колонке,
            // при DROP COLUMN, отдельный dropUnique() не нужен.
            $table->dropColumn(['source_uuid', 'sales_rating', 'synced_at']);

            // Заполняется ResolveFlagsStage при импорте (create-only, см.
            // PersistProductStage) из GeneralSettings::$product_flags + wu/mss/promo.
            $table->json('flags')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Точный откат к исходной схеме невозможен: значения source_uuid/
     * sales_rating/synced_at необратимо потеряны. Колонки восстанавливаются
     * nullable (без unique на source_uuid) только чтобы старые миграции,
     * ссылающиеся на них позиционно (после('sales_rating')), не сломались,
     * если этот rollback когда-нибудь понадобится в цепочке.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('flags');

            $table->uuid('source_uuid')->nullable()->after('id');
            $table->string('sales_rating', 64)->nullable()->after('brand');
            $table->timestamp('synced_at')->nullable()->after('status');
        });
    }
};
