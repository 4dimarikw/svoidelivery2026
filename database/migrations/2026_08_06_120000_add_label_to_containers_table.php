<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * code => короткое покупательское название (то же, что в ContainerSeeder).
     */
    private const LABELS = [
        'pet_keg' => 'ПЭТ кег',
        'pet' => 'ПЭТ',
        'can' => 'Жестяная банка',
        'glass_bottle' => 'Стеклянная бутылка',
        'gas_cylinder' => 'Газовый баллон',
        'tin_can' => 'Консервная банка',
        'piece' => 'Бутылка',
        'pack' => 'Пачка',
    ];

    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->string('label', 64)->nullable()->after('name');
        });

        foreach (self::LABELS as $code => $label) {
            DB::table('containers')->where('code', $code)->update(['label' => $label]);
        }
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
