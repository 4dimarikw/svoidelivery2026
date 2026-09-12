<?php

namespace Database\Seeders;

use ContentCms\Models\SiteSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class TermsPageSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $section = SiteSection::firstOrCreate(['key' => 'terms'], [
                'title' => 'Условия сервиса', 'route_name' => 'terms',
                'fragment' => '', 'sort_order' => 0, 'is_active' => true,
            ]);
            $section->blocks()->firstOrCreate(['key' => 'terms-text'], [
                'title' => 'Текст условий сервиса', 'type' => 'terms_text',
                'content' => ['body' => ''], 'sort_order' => 0, 'is_active' => true,
            ]);
        });
    }
}
