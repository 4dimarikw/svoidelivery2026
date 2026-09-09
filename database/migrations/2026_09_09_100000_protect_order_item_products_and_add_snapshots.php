<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE order_items DROP FOREIGN KEY order_items_product_id_foreign,
            ADD CONSTRAINT order_items_product_restrict_foreign FOREIGN KEY (product_id)
            REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
            ADD COLUMN product_name VARCHAR(512) NULL,
            ADD COLUMN product_external_code VARCHAR(64) NULL,
            ADD COLUMN product_brand VARCHAR(255) NULL');
    }

    public function down(): void
    {
        throw new RuntimeException('Откат снимков и защиты истории запрещён. Используйте отдельную сохраняющую данные миграцию.');
    }
};
