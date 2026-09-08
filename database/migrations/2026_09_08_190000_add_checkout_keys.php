<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->uuid('checkout_token')->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('checkout_key')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['checkout_key']);
            $table->dropColumn('checkout_key');
        });
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('checkout_token');
        });
    }
};
