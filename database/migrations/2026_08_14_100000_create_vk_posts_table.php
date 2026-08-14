<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Посты со стены VK-группы, забранные vk:sync-posts. Каждый пост
     * проходит ручную проверку в MoonShine перед рассылкой в Telegram —
     * поэтому здесь хранится и сырой текст (text, read-only), и
     * отредактированный вариант для рассылки (message_text).
     */
    public function up(): void
    {
        Schema::create('vk_posts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('owner_id');
            $table->bigInteger('vk_post_id');
            $table->string('post_type', 32);
            $table->timestamp('posted_at');
            $table->text('text')->nullable();
            $table->text('message_text')->nullable();
            $table->json('images');
            $table->json('rejected_images')->nullable();
            $table->json('raw');
            $table->string('status', 16)->default('draft');
            $table->timestamp('broadcast_at')->nullable();
            $table->json('broadcast_stats')->nullable();
            $table->timestamps();

            $table->unique(['owner_id', 'vk_post_id']);
            $table->index('status');
            $table->index('posted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vk_posts');
    }
};
