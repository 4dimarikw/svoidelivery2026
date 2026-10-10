<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LIMITS = [
        'products' => ['name' => 128],
        'categories' => ['name' => 32],
        'manufacturers' => ['name' => 40],
        'volumes' => ['label' => 16],
        'containers' => ['name' => 24, 'label' => 24],
    ];

    public function up(): void
    {
        // Проверяем все поля до изменения схемы, чтобы не обрезать существующие данные.
        foreach (self::LIMITS as $table => $fields) {
            foreach ($fields as $column => $limit) {
                if (DB::table($table)->whereRaw("CHAR_LENGTH(`{$column}`) > ?", [$limit])->exists()) {
                    throw new RuntimeException("Нельзя уменьшить {$table}.{$column}: найдены значения длиннее {$limit} символов.");
                }
            }
        }

        $this->changeLengths(self::LIMITS);
    }

    public function down(): void
    {
        $this->changeLengths([
            'products' => ['name' => 255],
            'categories' => ['name' => 255],
            'manufacturers' => ['name' => 255],
            'volumes' => ['label' => 32],
            'containers' => ['name' => 255, 'label' => 64],
        ]);
    }

    private function changeLengths(array $limits): void
    {
        foreach ($limits as $tableName => $fields) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $fields) {
                foreach ($fields as $column => $limit) {
                    $definition = $table->string($column, $limit);
                    if ($tableName === 'containers' && $column === 'label') {
                        $definition->nullable();
                    }
                    $definition->change();
                }
            });
        }
    }
};
