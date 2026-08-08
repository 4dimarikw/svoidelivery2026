<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('title');

            $table->boolean('redirect_to_pay')
                ->default(false);

        });

        DB::transaction(function () {
            DB::table('payment_methods')->insert([
                ['title' => 'При получении', 'redirect_to_pay' => false],
                ['title' => 'Наличными', 'redirect_to_pay' => false],
                ['title' => 'Онлайн', 'redirect_to_pay' => true],
            ]);
        });
    }

    public function down(): void
    {
        if (! app()->isProduction()) {
            Schema::dropIfExists('payment_methods');
        }
    }
};
