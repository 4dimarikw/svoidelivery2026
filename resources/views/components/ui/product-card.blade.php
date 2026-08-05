{{-- Карточка товара для каталога (главная, route `home`). Воспроизводит .card
     из брендбука (data/design-system/project/design-system.html, §06) в
     токенах Tailwind. <article>, не <a> — страницы товара не существует, см.
     CLAUDE.md про незаглушаемые мёртвые ссылки. --}}
@props(['product'])

@php
    $subParts = collect([$product->volume?->label, $product->container?->name])->filter();
@endphp

<article class="overflow-hidden rounded-sm border border-hairline bg-cream-50 transition hover:-translate-y-0.5 hover:shadow-md">
    <div class="aspect-[4/3] min-h-[180px] border-b border-hairline bg-cream-100">
        <img
            src="{{ $product->thumb }}"
            alt="{{ $product->name }}"
            loading="lazy"
            class="h-full w-full object-cover"
        >
    </div>

    <div class="px-5 pb-5 pt-4.5">
        <div class="mb-1.5 flex items-center justify-between">
            <div class="font-display text-btn-lg font-bold uppercase leading-none text-ink-900">{{ $product->name }}</div>
            <div class="font-mono text-caption text-ink-900">₽ {{ number_format((float) $product->price, 0, ',', ' ') }}</div>
        </div>

        @if ($subParts->isNotEmpty())
            <div class="text-caption text-ink-500">{{ $subParts->implode(' · ') }}</div>
        @endif

        @if ($product->is_new || $product->manufacturer || ! $product->in_stock)
            <div class="-ml-1.5 mt-3 flex flex-wrap [&>*]:ml-1.5 [&>*]:mt-1.5">
                @if ($product->is_new)
                    <x-ui.chip tone="rust">{{ __('catalog.new') }}</x-ui.chip>
                @endif
                @if ($product->manufacturer)
                    <x-ui.chip>{{ $product->manufacturer->name }}</x-ui.chip>
                @endif
                @if (! $product->in_stock)
                    <x-ui.chip tone="cream">{{ __('catalog.out_of_stock') }}</x-ui.chip>
                @endif
            </div>
        @endif
    </div>
</article>
