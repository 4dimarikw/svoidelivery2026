{{-- SVG-заглушка для карточки товара без собственного изображения (см.
     Product::hasOwnImage(), product-card.blade.php). Силуэт зависит от
     фактической тары товара (container->code), заливка — от типа напитка:
     нет beerDetails → аксессуар (cream-300), пивной стиль похож на
     лимонад/сидр → безалкогольный тон (teal-300), иначе → пиво (gold).
     Контур везде stroke-ink-300, 1.75px — параметры линий как у
     <x-ui.icon> (design system §иконография: linear, round caps). --}}
@props(['product'])

@php
    $containerCode = $product->container?->code;
    $styleName = mb_strtolower((string) $product->beerDetails?->beerStyle?->name);
    $isNonAlcoholicStyle = collect(['lemonade', 'cider', 'лимонад', 'сидр'])
        ->contains(fn (string $needle) => str_contains($styleName, $needle));

    $toneClass = match (true) {
        $product->beerDetails === null => 'fill-cream-300',
        $isNonAlcoholicStyle => 'fill-teal-300',
        default => 'fill-gold',
    };
@endphp

<div class="flex h-full w-full flex-col items-center justify-center">
    <svg
        viewBox="0 0 96 96"
        role="img"
        aria-label="{{ __('catalog.no_image') }}"
        data-container="{{ $containerCode ?? 'none' }}"
        class="h-10 w-10 xl:h-14 xl:w-14 {{ $toneClass }} stroke-ink-300"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        @switch($containerCode)
            {{-- Стеклянная бутылка / штучная бутылка (container_map: "ст. бут." / "бут") --}}
            @case('glass_bottle')
            @case('piece')
                <rect x="40" y="14" width="16" height="8" rx="2" />
                <rect x="42" y="20" width="12" height="20" rx="2" />
                <rect x="34" y="38" width="28" height="44" rx="6" />
                @break

            {{-- ПЭТ-бутылка: тот же силуэт + рёбра жёсткости у основания --}}
            @case('pet')
                <rect x="40" y="14" width="16" height="8" rx="2" />
                <rect x="42" y="20" width="12" height="20" rx="2" />
                <rect x="34" y="38" width="28" height="44" rx="6" />
                <line x1="34" y1="68" x2="62" y2="68" stroke-width="1.25" />
                <line x1="34" y1="74" x2="62" y2="74" stroke-width="1.25" />
                @break

            {{-- Банка: алюминиевая / консервная --}}
            @case('can')
            @case('tin_can')
                <rect x="32" y="26" width="32" height="52" rx="4" />
                <line x1="32" y1="34" x2="64" y2="34" stroke-width="1.25" />
                @break

            {{-- ПЭТ-кег: бочонок с горловиной --}}
            @case('pet_keg')
                <rect x="44" y="14" width="8" height="10" rx="2" />
                <path d="M34 24 C24 30 24 76 34 82 L62 82 C72 76 72 30 62 24 Z" />
                @break

            {{-- Пачка: картонная коробка с клапаном --}}
            @case('pack')
                <path d="M26 30 L48 18 L70 30" />
                <rect x="26" y="30" width="44" height="44" rx="3" />
                @break

            {{-- Газовый баллон --}}
            @case('gas_cylinder')
                <rect x="44" y="10" width="8" height="10" rx="2" />
                <rect x="38" y="20" width="20" height="58" rx="8" />
                <rect x="32" y="78" width="32" height="6" rx="2" />
                @break

            {{-- Тара не определена (аксессуары: бокалы, барный инвентарь) — нейтральная коробка --}}
            @default
                <rect x="26" y="32" width="44" height="40" rx="4" />
                <line x1="26" y1="48" x2="70" y2="48" stroke-width="1.25" />
                <line x1="48" y1="32" x2="48" y2="72" stroke-width="1.25" />
        @endswitch
    </svg>

    @if ($product->volume?->label)
        <span class="hidden font-mono text-micro uppercase tracking-meta text-ink-500 xl:mt-2 xl:block">{{ $product->volume->label }}</span>
    @endif
</div>
