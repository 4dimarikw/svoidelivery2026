<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * route_name = null теперь означает «глобальная секция», не привязанная
     * к конкретной странице/роуту — нужно подвалу сайта (Domain\Content\Actions\Content\LoadSiteFooter),
     * который рендерится на любой публичной странице сразу, в отличие от
     * обычных SiteSection, отдаваемых Domain\Content\Actions\Content\LoadPublicPage
     * по конкретному route_name.
     */
    public function up(): void
    {
        Schema::table('site_sections', function (Blueprint $table): void {
            $table->string('route_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('site_sections', function (Blueprint $table): void {
            $table->string('route_name')->nullable(false)->change();
        });
    }
};
