<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegraph_bots', function (Blueprint $table) {
            // Не часть схемы пакета defstudio/telegraph — у него только
            // token/name. username нужен для data-telegram-login виджета
            // входа и заполняется автоматически (см.
            // Domain\Telegram\Models\TelegramBot::booted()).
            $table->string('username')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('telegraph_bots', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
