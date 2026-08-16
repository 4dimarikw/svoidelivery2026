{{-- Диспетчер CMS-блоков (Domain\Content, docs/cms-ai-agent-guideline.md §10):
     для каждого опубликованного блока каждой секции выбирает Blade-компонент
     через config('content.views.'.$block->type) и проверяет его наличие —
     незарегистрированный/недомотанный тип молча не рендерится, а не падает
     с ошибкой. Ничего не запрашивает у БД: $sections приходит из
     Domain\Content\Actions\Content\LoadPublicPage::handle(), которая уже
     eager-load'ит publishedBlocks→publishedItems (+media) одним заходом. --}}
@props(['sections'])

@foreach ($sections as $section)
    @foreach ($section->publishedBlocks as $block)
        @php
            $view = config('content.views.'.$block->type);
            $component = $view ? \Illuminate\Support\Str::after($view, 'components.') : null;
        @endphp

        @if ($component && \Illuminate\Support\Facades\View::exists($view))
            <x-dynamic-component :component="$component" :block="$block" />
        @endif
    @endforeach
@endforeach
