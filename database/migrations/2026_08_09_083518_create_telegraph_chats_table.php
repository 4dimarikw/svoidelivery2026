<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegraph_chats', function (Blueprint $table) {
            $table->id();

            // Связь с users — идентичность телеграм-пользователя живёт здесь,
            // а не в отдельной колонке на users (см. Domain\Telegram\Models).
            // nullOnDelete, не cascade: удаление пользователя не обязано
            // стирать сам чат, тот может ещё пригодиться боту.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('chat_id');
            $table->string('name')->nullable();

            $table->foreignId('telegraph_bot_id')->constrained('telegraph_bots')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['chat_id', 'telegraph_bot_id']);
            // Тот же принцип, что unique(['chat_id', 'telegraph_bot_id']) —
            // один привязанный чат на пользователя на бота.
            $table->unique(['user_id', 'telegraph_bot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegraph_chats');
    }
};
