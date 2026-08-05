<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moonshine_permissions', static function (Blueprint $table): void {
            $table->id();

            $table->foreignId('moonshine_user_role_id')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('model');

            $table->json('permissions');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moonshine_permissions');
    }
};
