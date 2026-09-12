<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array RULES = [
        'beer' => [
            ['type' => 'alcohol', 'match_when' => 'default', 'value' => null],
        ],
        'mead' => [
            ['type' => 'alcohol', 'match_when' => 'style', 'value' => 'mead'],
        ],
        'cider' => [
            ['type' => 'alcohol', 'match_when' => 'style', 'value' => 'cider'],
        ],
        'non-alcoholic' => [
            ['type' => 'alcohol', 'match_when' => 'no_abv', 'value' => null],
        ],
        'sauce' => [
            ['type' => 'alcohol', 'match_when' => 'style', 'value' => 'sauce'],
        ],
        'pet-tare-packages' => [
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'пэт'],
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'тара'],
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'пакет'],
        ],
        'for-beer' => [
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'арахис'],
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'снэки'],
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'чипсы'],
        ],
        'clothes' => [
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'футболка'],
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'толстовка'],
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'шапка'],
        ],
        'attributes' => [
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'шеврон'],
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'маска'],
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'флаг'],
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'атрибутика'],
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'коврик'],
        ],
        'souvenirs' => [
            ['type' => 'alcohol', 'match_when' => 'advent', 'value' => null],
            ['type' => 'accessory_title', 'match_when' => null, 'value' => 'адвент'],
        ],
        'probes' => [
            ['type' => 'contains', 'match_when' => null, 'value' => 'пробники'],
        ],
        'souvenir-glasses' => [
            ['type' => 'contains', 'match_when' => null, 'value' => 'сувенирные бокалы'],
        ],
    ];

    /**
     * Правила определения категории обрабатываются CategorySlugResolver по priority. type принимает alcohol,
     * contains или accessory_title. Для alcohol поле match_when задаёт advent, no_abv, style или default. value
     * хранит ключевое слово стиля, подстроку поиска или отдельное слово названия аксессуара.
     */
    public function up(): void
    {
        Schema::create('category_match_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('match_when', 32)->nullable();
            $table->string('value')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'priority']);
        });

        $now = now();
        $priority = 0;
        foreach (self::RULES as $slug => $rules) {
            $categoryId = DB::table('categories')->where('slug', $slug)->sole()->id;
            foreach ($rules as $rule) {
                $priority += 10;
                DB::table('category_match_rules')->insert(array_merge($rule, [
                    'category_id' => $categoryId,
                    'priority' => $priority,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_match_rules');
    }
};
