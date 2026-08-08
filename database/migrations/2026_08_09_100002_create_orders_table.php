<?php

use Domain\Auth\Models\User;
use Domain\Order\Enums\OrderStatuses;
use Domain\Order\Models\DeliveryType;
use Domain\Order\Models\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('number', 20)->unique();

            $table->enum('status', array_column(OrderStatuses::cases(), 'value'))
                ->default('new')
                ->index();

            $table->foreignIdFor(User::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignIdFor(DeliveryType::class)
                ->default(1)
                ->constrained();

            $table->foreignIdFor(PaymentMethod::class)
                ->default(1)
                ->constrained();

            $table->text('comment')
                ->nullable();

            // decimal(12,2) — рубли, как products.price/cart_items.price;
            // Order::amount кастуется PriceCast (см. Support\Casts\PriceCast).
            $table->decimal('amount', 12, 2)
                ->default(0)
                ->index();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        if (! app()->isProduction()) {
            Schema::dropIfExists('orders');
        }
    }
};
