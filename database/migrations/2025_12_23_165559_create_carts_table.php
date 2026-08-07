<?php

use Domain\Auth\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            // Корзина только для авторизованных (Domain\Cart, см. CLAUDE.md/CartManager) —
            // одна корзина на пользователя, unique гарантирует это на уровне схемы.
            // Никакой гостевой identity (storage_id) — гость кнопку "Купить" не видит вовсе.
            $table->foreignIdFor(User::class)
                ->unique()
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! app()->isProduction()) {
            Schema::dropIfExists('carts');
        }
    }
};
