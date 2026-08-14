<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Журнал доставки поста конкретному пользователю. Даёт идемпотентность
     * рассылки (BroadcastVkPostJob не шлёт повторно тем, у кого уже есть
     * строка со статусом sent) и возможность разобраться, кому не дошло.
     */
    public function up(): void
    {
        Schema::create('vk_post_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vk_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['vk_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vk_post_deliveries');
    }
};
