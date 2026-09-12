<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array CATEGORIES = [
        'beer' => [
            'name' => 'Пиво',
            'expects_container' => true,
            'expects_volume' => true,
        ],
        'mead' => [
            'name' => 'Мёд',
            'expects_container' => true,
            'expects_volume' => true,
        ],
        'cider' => [
            'name' => 'Сидр',
            'expects_container' => true,
            'expects_volume' => true,
        ],
        'non-alcoholic' => [
            'name' => 'Безалкогольные напитки',
            'expects_container' => true,
            'expects_volume' => true,
        ],
        'sauce' => [
            'name' => 'Соус',
            'expects_container' => true,
            'expects_volume' => true,
        ],
        'not-defined' => [
            'name' => 'не определена',
        ],
        'pet-tare-packages' => [
            'name' => 'ПЭТ ТАРА ПАКЕТЫ',
            'expects_container' => true,
            'expects_volume' => true,
            'container_code' => 'pet',
        ],
        'for-beer' => [
            'name' => 'К пиву',
        ],
        'clothes' => [
            'name' => 'Одежда',
        ],
        'attributes' => [
            'name' => 'Атрибутика',
        ],
        'souvenirs' => [
            'name' => 'Сувениры',
        ],
        'probes' => [
            'name' => 'Пробники',
            'expects_container' => true,
            'expects_volume' => true,
        ],
        'souvenir-glasses' => [
            'name' => 'Сувенирные бокалы',
        ],
    ];

    /**
     * Параметры категорий управляют этапами импорта: требования к таре и объёму, обработка цены, название из
     * артикула и значения бренда и тары по умолчанию.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('expects_container')->default(false);
            $table->boolean('expects_volume')->default(false);
            $table->boolean('price_exempt')->default(false);
            $table->boolean('name_from_article')->default(false);
            $table->string('default_brand')->nullable();
            // Код тары сопоставляется с containers.code в ResolveContainerStage; внешний ключ не используется.
            $table->string('container_code', 64)->nullable();
            $table->timestamps();
        });

        $now = now();
        foreach (self::CATEGORIES as $slug => $attributes) {
            DB::table('categories')->insert(array_merge($attributes, [
                'slug' => $slug,
                'code' => str_replace('-', '_', $slug),
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
