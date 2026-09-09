<?php

use Domain\Order\Actions\BackfillOrderItemSnapshots;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(BackfillOrderItemSnapshots::class)->execute();
    }

    public function down(): void
    {
        throw new RuntimeException('Снимки истории не удаляются при откате.');
    }
};
