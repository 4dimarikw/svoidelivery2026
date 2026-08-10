<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Части адреса (street/house/apartment/entrance/floor/intercom)
     * схлопываются в одно свободное поле address — и в addresses, и в
     * снимке order_customers (см. Domain\Order\Models\OrderCustomer —
     * снимок обязан зеркалить структуру Address). city остаётся отдельно.
     */
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('address')->nullable()->after('city');
        });

        $this->backfill('addresses');

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['street', 'house', 'apartment', 'entrance', 'floor', 'intercom']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            // Обязательное поле формы — в отличие от order_customers, где
            // самовывоз оставляет адрес пустым целиком.
            $table->string('address')->nullable(false)->change();
        });

        Schema::table('order_customers', function (Blueprint $table) {
            $table->string('address')->nullable()->after('city');
        });

        $this->backfill('order_customers');

        Schema::table('order_customers', function (Blueprint $table) {
            $table->dropColumn(['street', 'house', 'apartment', 'entrance', 'floor', 'intercom']);
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('street')->nullable()->after('city');
            $table->string('house')->nullable();
            $table->string('apartment')->nullable();
            $table->string('entrance')->nullable();
            $table->string('floor')->nullable();
            $table->string('intercom')->nullable();
        });

        DB::table('addresses')->update(['street' => DB::raw('address')]);

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('address');
        });

        Schema::table('order_customers', function (Blueprint $table) {
            $table->string('street')->nullable()->after('city');
            $table->string('house')->nullable();
            $table->string('apartment')->nullable();
            $table->string('entrance')->nullable();
            $table->string('floor')->nullable();
            $table->string('intercom')->nullable();
        });

        DB::table('order_customers')->update(['street' => DB::raw('address')]);

        Schema::table('order_customers', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }

    /**
     * Склеивает старые части адреса в одну строку — тот же порядок и
     * подписи, что был в UploadOrderToFTP::formatAddress(): "улица,
     * д. N, кв. N, подъезд N, этаж N". Пустые части (NULL или '')
     * выпадают из склейки за счёт CONCAT_WS + NULLIF.
     */
    private function backfill(string $table): void
    {
        DB::statement(<<<SQL
            UPDATE `{$table}` SET `address` = NULLIF(CONCAT_WS(', ',
                NULLIF(street, ''),
                CASE WHEN house IS NOT NULL AND house != '' THEN CONCAT('д. ', house) ELSE NULL END,
                CASE WHEN apartment IS NOT NULL AND apartment != '' THEN CONCAT('кв. ', apartment) ELSE NULL END,
                CASE WHEN entrance IS NOT NULL AND entrance != '' THEN CONCAT('подъезд ', entrance) ELSE NULL END,
                CASE WHEN floor IS NOT NULL AND floor != '' THEN CONCAT('этаж ', floor) ELSE NULL END,
                CASE WHEN intercom IS NOT NULL AND intercom != '' THEN CONCAT('домофон ', intercom) ELSE NULL END
            ), '')
        SQL);
    }
};
