{{-- Рендер блока типа about_info (Domain\Content\Types\AboutInfoBlockType) —
     страница «О нас» (route `about`). $block приходит из
     <x-content.sections> уже с eager-loaded publishedItems, здесь только
     раскладываем content/items по вёрстке. Безопасное чтение JSON-полей
     (?? '' / ?? []) — записи могут быть созданы до появления нового поля,
     см. §7.1 docs/cms-ai-agent-guideline.md. Текст выводится только через
     {{ }} — экранирование обязательно для CMS-контента (§4/§10). --}}
@props(['block'])

@php
    $content = $block->content ?? [];
    $rules = $block->publishedItems->where('group_key', 'ordering_rules');
    $promotions = $block->publishedItems->where('group_key', 'promotions');
@endphp

<h1 class="font-display text-heading-m uppercase text-ink-900 sm:text-display-l">{{ $content['heading'] ?? '' }}</h1>

<div class="mt-6 max-w-2xl space-y-4 text-body-l text-ink-700">
    @if (! empty($content['lead']))
        <p>{{ $content['lead'] }}</p>
    @endif

    @if (! empty($content['schedule']))
        <p>{{ $content['schedule'] }}</p>
    @endif

    @if (! empty($content['ordering_heading']) && $rules->isNotEmpty())
        <div>
            <p class="font-semibold text-ink-900">{{ $content['ordering_heading'] }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($rules as $rule)
                    <li>{{ $rule->content['text'] ?? '' }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($content['promo_lead']) && $promotions->isNotEmpty())
        <div>
            <p class="font-semibold text-ink-900">{{ $content['promo_lead'] }}</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($promotions as $promo)
                    <li>{{ $promo->content['text'] ?? '' }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($content['notice']))
        <x-ui.alert tone="warn">
            <span class="font-semibold">{{ $content['notice'] }}</span>
        </x-ui.alert>
    @endif
</div>
