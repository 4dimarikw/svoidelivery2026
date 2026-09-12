<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('channel', 16)->default('ftp');
            $table->unsignedInteger('version');
            $table->uuid('operation_key')->unique();
            $table->string('source', 32);
            $table->longText('xml');
            $table->string('disk', 64);
            $table->text('ftp_path');
            $table->string('state', 16)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('cycle_attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->uuid('lock_token')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'channel', 'version']);
            $table->index(['state', 'next_attempt_at']);
            $table->index(['state', 'locked_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_deliveries');
    }
};
