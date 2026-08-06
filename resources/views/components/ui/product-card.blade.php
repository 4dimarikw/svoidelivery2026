{{-- Карточка товара для каталога (главная, route `home`). Воспроизводит .card
     из брендбука (data/design-system/project/design-system.html, §06) в
     токенах Tailwind. <article>, не <a> — страницы товара не существует, см.
     CLAUDE.md про незаглушаемые мёртвые ссылки.

     Заголовок — products.brand ("Марка" из 1С), а не name: name — сырая
     склейка 1С вида `Big Village "OLD SONG" (DDH NEDIPA) алк. 8,5% /
     Хейзи Дабл ИПА, ж/б 0,45л`, всё содержимое которой карточка уже
     показывает отдельными полями (производитель/тара/объём/стиль/ABV) —
     как заголовок она нечитаема и дублирует остальную карточку. name всё
     равно остаётся в разметке — в alt картинки, когда она есть, и всегда
     в title <article> (родной html-тултип), иначе у товаров без
     собственного изображения (заглушка вместо <img>, см. ниже) name нигде
     не появлялся бы вовсе.

     $product->beerDetails (HasOne на beer_product_details) есть только у
     товаров, для которых импорт нашёл хотя бы один пивной атрибут — у
     аксессуаров её нет вовсе, поэтому 4-я строка (abv/ibu/plato/ebc) и чип
     рейтинга рендерятся только когда есть что показывать. Требует
     CatalogController::index() eager-load 'beerDetails.beerStyle' и
     'beerDetails.untappdBeer' — без этого N+1 на каждую карточку.

     $product->hasOwnImage() отличает настоящую медиа-картинку от
     заглушки Untappd (badge-beer-default-thumb) — при false рендерится
     <x-ui.product-placeholder> вместо <img>. Бьёт по уже eager-loaded
     'media', нового N+1 не создаёт. --}}
@props(['product'])

@php
    $beer = $product->beerDetails;

    // brand пуст только у товаров, у которых 1С не прислала "Марку" —
    // на сегодня таких нет, но колонка nullable, поэтому fallback.
    $title = $product->brand ?: $product->name;

    // Объём · тара · пивной стиль.
    $spec = collect([
        $product->volume?->label,
        $product->container?->label ?: $product->container?->name,
        $beer?->beerStyle?->name,
    ])->filter();

    // Крепость/горечь/плотность/цвет — только пиво, каждое поле независимо
    // nullable даже когда $beer сама по себе существует.
    $numbers = collect([
        ($v = spec_number($beer?->abv)) ? $v.'%' : null,
        ($v = spec_number($beer?->ibu)) ? $v.' '.__('catalog.spec.ibu') : null,
        ($v = spec_number($beer?->plato)) ? $v.' '.__('catalog.spec.plato') : null,
        ($v = spec_number($beer?->ebc)) ? $v.' '.__('catalog.spec.ebc') : null,
    ])->filter();

    $rating = spec_number($beer?->untappdBeer?->rating_score);
@endphp

<article
    title="{{ $product->name }}"
    class="overflow-hidden rounded-sm border border-hairline bg-cream-50 transition hover:-translate-y-0.5 hover:shadow-md"
>
    <div class="aspect-[4/3] min-h-[180px] border-b border-hairline bg-cream-100">
        @if ($product->hasOwnImage())
            <img
                src="{{ $product->thumb }}"
                alt="{{ $product->name }}"
                loading="lazy"
                class="h-full w-full object-cover"
            >
        @else
            <x-ui.product-placeholder :product="$product" />
        @endif
    </div>

    <div class="px-5 pb-5 pt-4.5">
        <div class="mb-1.5 flex items-start justify-between">
            <div class="min-w-0 line-clamp-2 font-display text-btn-lg font-bold uppercase leading-[1.15] text-ink-900">{{ $title }}</div>
            <div class="ml-3 shrink-0 whitespace-nowrap font-mono text-caption text-ink-900">₽ {{ number_format((float) $product->price, 0, ',', ' ') }}</div>
        </div>

        @if ($product->manufacturer)
            <div class="text-caption text-ink-500">{{ $product->manufacturer->name }}</div>
        @endif

        @if ($spec->isNotEmpty())
            <div class="mt-0.5 text-caption text-ink-500">{{ $spec->implode(' · ') }}</div>
        @endif

        @if ($numbers->isNotEmpty())
            <div class="mt-1.5 font-mono text-micro tracking-meta text-ink-500">{{ $numbers->implode(' · ') }}</div>
        @endif

        @if ($product->is_new || $rating || ! $product->in_stock)
            <div class="-ml-1.5 mt-3 flex flex-wrap [&>*]:ml-1.5 [&>*]:mt-1.5">
                @if ($product->is_new)
                    <x-ui.chip tone="rust">{{ __('catalog.new') }}</x-ui.chip>
                @endif
                @if ($rating)
                    <x-ui.chip tone="teal"><x-ui.icon name="star" :size="12" class="mr-1" />{{ $rating }}</x-ui.chip>
                @endif
                @if (! $product->in_stock)
                    <x-ui.chip tone="cream">{{ __('catalog.out_of_stock') }}</x-ui.chip>
                @endif
            </div>
        @endif
    </div>
</article>
