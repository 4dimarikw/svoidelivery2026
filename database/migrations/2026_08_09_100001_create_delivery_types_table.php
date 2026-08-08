<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_types', function (Blueprint $table) {
            $table->id();

            $table->string('title', 100)
                ->unique()
                ->index();

            // decimal(12,2) — рубли, как products.price/cart_items.price;
            // DeliveryType::price кастуется PriceCast, который документирован
            // именно под этот тип колонки (см. Support\Casts\PriceCast).
            $table->decimal('price', 12, 2)
                ->default(0)
                ->index();

            $table->boolean('with_address')
                ->default(false)
                ->index();

            $table->timestamps();
        });

        DB::transaction(function () {
            DB::table('delivery_types')->insert([
                [
                    'title' => 'Служба доставки',
                    'with_address' => true,
                    'price' => 0,
                ],
                [
                    'title' => 'Самовывоз',
                    'with_address' => false,
                    'price' => 0,
                ],
                [
                    'title' => 'Курьером',
                    'with_address' => true,
                    'price' => 0,
                ],
            ]);
        });
    }

    public function down(): void
    {
        if (! app()->isProduction()) {
            Schema::dropIfExists('delivery_types');
        }
    }
};
