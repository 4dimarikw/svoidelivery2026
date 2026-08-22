<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_customers', function (Blueprint $table) {
            $table->string('messenger_url')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('order_customers', function (Blueprint $table) {
            $table->dropColumn('messenger_url');
        });
    }
};
