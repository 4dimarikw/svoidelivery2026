<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Services\CatalogImport\CategoryMatchingRules;

return new class extends Migration
{
    public function up(): void
    {
        $configured = $this->rules();
        // LEFT JOIN сохраняет повреждённые ссылки: они тоже должны остановить удаление.
        $stored = DB::table('category_match_rules as rules')
            ->leftJoin('categories', 'categories.id', '=', 'rules.category_id')
            ->orderBy('rules.priority')->orderBy('rules.id')
            ->get(['categories.slug', 'rules.type', 'rules.match_when', 'rules.value', 'rules.priority', 'rules.is_active'])
            ->map(static fn ($rule) => [
                'slug' => $rule->slug,
                'type' => $rule->type,
                'match_when' => $rule->match_when,
                'value' => $rule->value,
                'priority' => (int) $rule->priority,
                'is_active' => (bool) $rule->is_active,
            ])->all();

        if ($stored !== $configured) {
            throw new RuntimeException('Правила БД отличаются от category_matching.rules. Перенесите все записи в порядке priority, id, включая отключённые; таблица сохранена.');
        }

        Schema::drop('category_match_rules');
    }

    public function down(): void
    {
        $rules = $this->rules();
        $categories = DB::table('categories')->pluck('id', 'slug');
        foreach ($rules as $rule) {
            if (! isset($categories[$rule['slug']])) {
                throw new RuntimeException('Нельзя восстановить правила: отсутствует категория '.$rule['slug']);
            }
        }

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
        foreach ($rules as $rule) {
            $categoryId = $categories[$rule['slug']];
            unset($rule['slug']);
            DB::table('category_match_rules')->insert($rule + [
                'category_id' => $categoryId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function rules(): array
    {
        return array_map(static fn (array $rule) => [
            'slug' => $rule['slug'], 'type' => $rule['type'],
            'match_when' => $rule['match_when'], 'value' => $rule['value'],
            'priority' => $rule['priority'], 'is_active' => $rule['is_active'],
        ], CategoryMatchingRules::ordered(config('category_matching.rules')));
    }
};
