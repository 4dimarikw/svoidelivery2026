<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegraph_chats', function (Blueprint $table) {
            // Зеркало user_id (см. 2026_08_09_083518_create_telegraph_chats_table.php):
            // тот же чат может быть привязан ещё и к админу MoonShine — для кнопки
            // «Отправить себе» на странице VK-постов. nullOnDelete, не cascade —
            // по тем же причинам, что и у user_id.
            $table->foreignId('moonshine_user_id')->nullable()->after('user_id')
                ->constrained('moonshine_users')->nullOnDelete();

            // Один привязанный чат на админа на бота — тот же принцип,
            // что и unique(['user_id', 'telegraph_bot_id']).
            $table->unique(['moonshine_user_id', 'telegraph_bot_id']);
        });
    }

    public function down(): void
    {
        Schema::table('telegraph_chats', function (Blueprint $table) {
            $table->dropUnique(['moonshine_user_id', 'telegraph_bot_id']);
            $table->dropConstrainedForeignId('moonshine_user_id');
        });
    }
};
