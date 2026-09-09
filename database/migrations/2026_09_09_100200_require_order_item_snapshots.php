<?php

use Domain\Order\Actions\BackfillOrderItemSnapshots;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        app(BackfillOrderItemSnapshots::class)->assertComplete();
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_name', 512)->nullable(false)->change();
            $table->string('product_external_code', 64)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Ослабление защиты снимков требует отдельной миграции.');
    }
};
