@php use Domain\Cart\CartManager; @endphp
{{-- Карточка товара использует общую кнопку избранного на изображении. Цвет сердца задаётся динамически по
   favorited; статичный класс цвета дублировать нельзя, поскольку Alpine не удаляет авторские классы. Светлая
   обводка и drop-shadow-fav сохраняют читаемость на фото. overflow-hidden на product-card-thumb позволяет
   квадратному превью уменьшаться внутри flex-контейнера независимо от исходной высоты изображения. --}}
@props(['product', 'removeOnUnfavorite' => false])

@php
    $beer = $product->beerDetails;
    $beerStyleName = $beer?->beerStyle?->name;

    // При пустом brand используется резервное название; колонка brand допускает null.
    $title = $product->brand ?: $product->name;

    // Объём · тара (стиль — отдельной плашкой выше, не в этой строке).
    $spec = collect([
        $product->volume?->label,
        $product->container?->label ?: $product->container?->name,
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

    // Аналогично избранному (<x-ui.favorite-toggle>) — только для
    // авторизованных, мемоизировано в CartManager на один HTTP-запрос (см.
    // Domain\Cart\CartManager::items()).
    $cartQuantity = auth()->check() ? app(CartManager::class)->quantityOf($product) : 0;
    $available = $product->isAvailable();
@endphp

{{-- $removeOnUnfavorite — только со страницы /account/favorites (см.
     account/favorites/index.blade.php, форвардится через
     pages/catalog/_cards.blade.php). На каталоге снятие с избранного не
     должно убирать карточку из сетки, поэтому x-data/x-show/listener ниже
     добавляются только в этом режиме, тот же приём, что у cart-line.blade.php
     для cart:item-removed. --}}
<article
    title="{{ $product->name }}"
    class="flex h-full flex-col overflow-hidden rounded-sm border border-hairline bg-cream-50 transition hover:-translate-y-0.5 hover:shadow-md"
    @if ($removeOnUnfavorite)
        x-data="{ removed: false }"
        x-show="! removed"
        x-on:favorite:removed.window="if ($event.detail.productId === {{ $product->id }}) removed = true"
    @endif
>
    {{-- ! $available — фото и текст карточки приглушены (grayscale/opacity),
         чтобы состояние читалось с первого взгляда без наведения на чип; чип
         «нет в наличии» (ниже) и кнопка «Удалить из корзины»
         (cart-stepper.blade.php) намеренно НЕ затемнены — они и есть
         единственный контраст, который должен привлекать внимание. --}}
    <div @class(['relative aspect-square overflow-hidden bg-cream-100', 'grayscale opacity-50' => ! $available])>
        <a href="{{ route('product.show', $product) }}" aria-label="{{ __('catalog.go_to_product') }}"
           class="product-card-thumb block h-full w-full">
            @if ($product->hasOwnImage())
                <img
                    src="{{ $product->thumb }}"
                    alt="{{ $product->name }}"
                    loading="lazy"
                    class="h-full w-full object-cover"
                >
            @else
                <x-ui.product-placeholder :product="$product"/>
            @endif
        </a>

        <x-ui.favorite-toggle :product="$product" class="absolute right-2 top-2 z-10"/>

        {{-- Бейдж «Новинка» находится у левого края нижней кромки изображения и скруглён справа. --}}
        @if ($product->is_new)
            <span
                class="absolute bottom-2 left-0 z-10 inline-flex items-center rounded-r-sm bg-cream-200 px-3 pb-[5px] pt-1.5 font-mono text-badge font-medium uppercase leading-none tracking-label text-tan-600">
                {{ __('catalog.new') }}
            </span>
        @endif

        {{-- Рейтинг Untappd находится у правого края нижней кромки изображения и скруглён слева. --}}
        @if ($rating)
            <span
                class="absolute bottom-2 right-0 z-10 inline-flex items-center gap-1 rounded-l-sm bg-cream-50/90 px-2.5 pb-1 pt-[5px] font-mono text-xs font-medium leading-none tracking-[0.02em] text-untappd">
                <x-ui.icon-untappd :size="13"/>{{ $rating }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col px-3.5 pt-[13px] pb-[15px]">
        @if ($product->manufacturer)
            <div @class(['font-mono text-badge uppercase text-ink-300', 'opacity-60' => ! $available])>{{ $product->manufacturer->name }}</div>
        @endif

        <div @class(['mt-1 line-clamp-2 font-display text-caption font-bold uppercase leading-[1.15] tracking-brand-snug text-ink-900 xl:text-btn-lg', 'opacity-60' => ! $available])>{{ $title }}</div>

        {{-- Плашка стиля использует светлые teal-100 и teal-800, отличаясь от заливки кнопки покупки как
           некликабельная метка. --}}
        @if ($beerStyleName)
            <div @class(['mt-1.5 inline-flex self-start items-center rounded-sm bg-teal-100 px-1.5 py-0.5 font-display text-micro font-semibold uppercase tracking-[0.06em] text-teal-800 xl:px-2.5 xl:py-1', 'opacity-60' => ! $available])>{{ $beerStyleName }}</div>
        @endif

        @if ($spec->isNotEmpty())
            <div @class(['mt-1.5 text-micro text-ink-500 xl:text-caption', 'opacity-60' => ! $available])>{{ $spec->implode(' · ') }}</div>
        @endif

        @if ($numbers->isNotEmpty())
            <div @class(['mt-1.5 font-mono text-[11px] leading-4 tracking-[0.06em] text-teal-800', 'opacity-60' => ! $available])>{{ $numbers->implode(' · ') }}</div>
        @endif

        {{-- Распорка: тело карточки — flex flex-1 flex-col, поэтому auto-margin
             здесь съедает всё свободное место и прижимает чипы + .actions к
             низу карточки одинаково у всех карточек ряда — независимо от
             того, сколько переменного контента (плашка стиля/спеки/ABV) выше.
             Именно здесь, не на .actions: у гостя .actions нет вовсе (@auth
             ниже), без этой распорки чипы гостевых карточек стояли бы на
             разной высоте. --}}
        <div class="mt-auto"></div>

        @if (! $available)
            <div class="-ml-1.5 mt-3 flex flex-wrap [&>*]:ml-1.5 [&>*]:mt-1.5">
                {{-- Важные утилиты padding до xl перекрывают стандартные отступы chip.blade.php независимо от
                   порядка классов. На xl используются стандартные отступы. Рейтинг и новинка выводятся
                   отдельными бейджами на изображении. --}}
                <x-ui.chip tone="cream" class="max-xl:!px-1.5 max-xl:!py-1">{{ __('catalog.out_of_stock') }}</x-ui.chip>
            </div>
        @endif

        {{-- Зазор чипы → линия фиксированный (mt-2.5, симметрично pt-2.5
             под линией; xl: возвращает брендбучные 3.5) — низ карточки
             прижимает распорка mt-auto выше, не эта строка.

             Избранное — на картинке (см. комментарий вверху файла), здесь
             только цена и степпер, друг под другом: в строку они не
             помещаются ни на одной колонке (3/4/5), включая xl. Высота
             степпера всё равно фиксирована — h-[41px], как .card .actions .qty{height:41px}
             в брендбуке (design-system.html §06/§10): без этого при qty=0
             блок тянет ~46px кнопка «Купить» (<x-ui.btn size="md">, padding
             12px + line-height 20px), а при qty>0 (кнопка скрыта x-show) —
             степпер своей высоты не имеет и схлопывается по цифре (~28px),
             из-за чего у карточки, на которой уже нажали «Купить», кнопки
             становятся заметно меньше, чем у соседних. --}}
        @auth
            <div class="mt-2.5 border-t border-hairline pt-2.5 xl:mt-3.5 xl:pt-3.5">
                <span @class(['block whitespace-nowrap font-display text-btn-lg font-bold tracking-brand-body text-ink-900 xl:text-heading-s', 'opacity-60' => ! $available])>{{ $product->price }}</span>

                <x-ui.cart-stepper :product="$product" :quantity="$cartQuantity" class="mt-2 h-[41px]"/>
            </div>
        @endauth
    </div>
</article>
