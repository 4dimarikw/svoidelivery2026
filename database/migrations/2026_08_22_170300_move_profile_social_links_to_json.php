<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Схлопываем telegram_url/vk_url в одну json-колонку social_links —
     * см. Domain\Profile\Models\Profile и config/social.php. Новая сеть
     * (MAX и далее) теперь не требует миграции.
     */
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->json('social_links')->nullable()->after('phone');
        });

        DB::table('user_profiles')->orderBy('id')->chunkById(200, function ($profiles) {
            foreach ($profiles as $profile) {
                $links = array_filter([
                    'telegram' => $profile->telegram_url,
                    'vk' => $profile->vk_url,
                ], fn (?string $url) => $url !== null && $url !== '');

                DB::table('user_profiles')
                    ->where('id', $profile->id)
                    ->update(['social_links' => $links === [] ? null : json_encode($links)]);
            }
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['telegram_url', 'vk_url']);
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('telegram_url')->nullable()->after('phone');
            $table->string('vk_url')->nullable()->after('phone');
        });

        DB::table('user_profiles')->orderBy('id')->chunkById(200, function ($profiles) {
            foreach ($profiles as $profile) {
                $links = json_decode((string) $profile->social_links, true) ?? [];

                DB::table('user_profiles')
                    ->where('id', $profile->id)
                    ->update([
                        'telegram_url' => $links['telegram'] ?? null,
                        'vk_url' => $links['vk'] ?? null,
                    ]);
            }
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('social_links');
        });
    }
};
