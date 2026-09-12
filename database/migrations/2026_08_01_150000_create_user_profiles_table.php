<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('patronymic')->nullable();
            $table->string('phone')->nullable();
            $table->json('social_links')->nullable();
            // NULL означает отсутствие проверки доступности бота.
            $table->boolean('is_bot_active')->nullable();
            $table->text('last_bot_error')->nullable();
            $table->timestamp('bot_checked_at')->nullable();
            $table->text('default_order_comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
