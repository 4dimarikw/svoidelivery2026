<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('moonshine_permissions')
            ->select('moonshine_user_role_id', 'model')
            ->groupBy('moonshine_user_role_id', 'model')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $details = $duplicates->map(function (object $pair): string {
                $ids = DB::table('moonshine_permissions')
                    ->where('moonshine_user_role_id', $pair->moonshine_user_role_id)
                    ->where('model', $pair->model)
                    ->orderBy('id')->pluck('id')->implode(', ');

                return "role={$pair->moonshine_user_role_id}, model={$pair->model}, ids=[{$ids}]";
            })->implode("\n");

            throw new RuntimeException("Устраните дубли прав вручную перед миграцией:\n".$details);
        }

        Schema::table('moonshine_permissions', function (Blueprint $table): void {
            $table->unique(['moonshine_user_role_id', 'model'], 'moonshine_permissions_role_model_unique');
        });
    }

    public function down(): void
    {
        // MySQL may remove the original FK index once the composite index covers it.
        // Restore it before dropping the unique index to keep the foreign key valid.
        if (! Schema::hasIndex('moonshine_permissions', ['moonshine_user_role_id'])) {
            Schema::table('moonshine_permissions', function (Blueprint $table): void {
                $table->index('moonshine_user_role_id', 'moonshine_permissions_moonshine_user_role_id_foreign');
            });
        }

        Schema::table('moonshine_permissions', function (Blueprint $table): void {
            $table->dropUnique('moonshine_permissions_role_model_unique');
        });
    }
};
