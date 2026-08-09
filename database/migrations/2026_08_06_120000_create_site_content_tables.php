<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->string('route_name');
            $table->string('fragment')->default('');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['route_name', 'fragment']);
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('site_menus', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('site_menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('site_menu_items')->cascadeOnDelete();
            $table->unsignedInteger('_lft')->default(0);
            $table->unsignedInteger('_rgt')->default(0);
            $table->foreignId('site_section_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('external_url', 2048)->nullable();
            $table->string('label')->nullable();
            $table->boolean('open_in_new_tab')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['site_menu_id', '_lft'], 'site_menu_items_menu_lft_idx');
            $table->index(['site_menu_id', '_rgt'], 'site_menu_items_menu_rgt_idx');
            $table->index(['site_menu_id', 'parent_id', '_lft'], 'site_menu_items_menu_parent_lft_idx');
            $table->index(['site_menu_id', 'is_active', '_lft'], 'site_menu_items_menu_active_lft_idx');
        });

        Schema::create('content_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_section_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('type');
            $table->string('title');
            $table->json('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['site_section_id', 'key']);
            $table->index(['is_active', 'sort_order']);
            $table->index(['site_section_id', 'sort_order']);
        });

        Schema::create('content_block_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_block_id')->constrained()->cascadeOnDelete();
            $table->string('group_key');
            $table->string('key');
            $table->string('title');
            $table->json('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['content_block_id', 'group_key', 'key']);
            $table->index(['is_active', 'sort_order']);
            $table->index(['content_block_id', 'group_key', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_block_items');
        Schema::dropIfExists('content_blocks');
        Schema::dropIfExists('site_menu_items');
        Schema::dropIfExists('site_menus');
        Schema::dropIfExists('site_sections');
    }
};
