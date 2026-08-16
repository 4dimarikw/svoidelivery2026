{{-- Контент страницы полностью CMS-driven (Domain\Content) — см.
     docs/cms-ai-agent-guideline.md. $sections приходит из
     App\Http\Controllers\PageController::about() →
     Domain\Content\Actions\Content\LoadPublicPage::handle('about');
     <x-content.sections> сама выбирает Blade-компонент под тип каждого
     опубликованного блока (сейчас — единственный блок about_info,
     см. resources/views/components/content/about-info.blade.php). --}}
<x-layouts.site>
    <div class="mx-auto max-w-page px-6 py-10">
        <x-content.sections :sections="$sections" />
    </div>
</x-layouts.site>
