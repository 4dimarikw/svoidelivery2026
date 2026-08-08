<?php

use Domain\Order\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_customers', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Order::class)
                ->unique()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('first_name')
                ->nullable();

            $table->string('last_name')
                ->nullable()
                ->index();

            $table->string('phone')
                ->nullable()
                ->index();

            // Снимок Domain\Profile\Models\Address на момент заказа (та же
            // логика, что у CartItem::price — цена/адрес снимаются в момент
            // действия, не живая ссылка на изменяемую запись). Все поля
            // nullable — пусто целиком у доставки без адреса (самовывоз).
            $table->string('city')
                ->nullable()
                ->index();

            $table->string('street')
                ->nullable();

            $table->string('house')
                ->nullable();

            $table->string('apartment')
                ->nullable();

            $table->string('entrance')
                ->nullable();

            $table->string('floor')
                ->nullable();

            $table->string('intercom')
                ->nullable();

            $table->text('comment')
                ->nullable();

            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index(['city', 'last_name']);
        });
    }

    public function down(): void
    {
        if (! app()->isProduction()) {
            Schema::dropIfExists('order_customers');
        }
    }
};
