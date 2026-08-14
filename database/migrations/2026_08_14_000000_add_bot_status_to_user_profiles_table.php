<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // NULL — ещё не проверяли; true/false — результат последней
            // проверки CheckTelegramBotAvailabilityAction (sendChatAction).
            $table->boolean('is_bot_active')->nullable()->after('telegram_url');
            $table->text('last_bot_error')->nullable()->after('is_bot_active');
            $table->timestamp('bot_checked_at')->nullable()->after('last_bot_error');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['is_bot_active', 'last_bot_error', 'bot_checked_at']);
        });
    }
};
