<?php

use Domain\Catalog\Enums\ProductStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status', 32)->default(ProductStatus::DRAFT->value)->after('sales_rating');
        });

        DB::table('products')->where('is_active', true)->update(['status' => ProductStatus::PUBLISHED->value]);
        DB::table('products')->where('is_active', false)->update(['status' => ProductStatus::ARCHIVED->value]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_active_stock_id_idx');
            $table->dropColumn('is_active');
            $table->index(['status', 'in_stock', 'id'], 'products_status_stock_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('sales_rating');
        });

        DB::table('products')->where('status', ProductStatus::PUBLISHED->value)->update(['is_active' => true]);
        DB::table('products')->where('status', '!=', ProductStatus::PUBLISHED->value)->update(['is_active' => false]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_status_stock_id_idx');
            $table->dropColumn('status');
            $table->index(['is_active', 'in_stock', 'id'], 'products_active_stock_id_idx');
        });
    }
};
