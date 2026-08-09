<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegraph_chats', function (Blueprint $table) {
            // Связь с users — идентичность телеграм-пользователя живёт здесь,
            // а не в отдельной колонке на users (см. Domain\Telegram\Models).
            // nullOnDelete, не cascade: удаление пользователя не обязано
            // стирать сам чат, тот может ещё пригодиться боту.
            $table->foreignId('user_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();

            // Тот же принцип, что unique(['chat_id', 'telegraph_bot_id']) у
            // самого пакета (create_telegraph_chats_table.php) — один
            // привязанный чат на пользователя на бота.
            $table->unique(['user_id', 'telegraph_bot_id']);
        });
    }

    public function down(): void
    {
        Schema::table('telegraph_chats', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'telegraph_bot_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
