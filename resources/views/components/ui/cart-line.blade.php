{{-- Строка корзины (/cart, pages/cart.blade.php) — воспроизводит .cart-line
     из брендбука (design-system.html §10 «Корзина и оформление»): превью
     56×56 → название+спека → степпер → сумма строки → крестик-удаление,
     через CSS Grid (не flex — grid `gap` безопасен для Safari >= 13.1, флекс-
     gap запрещён CLAUDE.md только для Safari < 14.1).

     Цена строки — две строчки в одной ячейке: сверху расшифровка «2 × ₽ 400»
     (количество × цена за штуку, CartItem::price — снятый снапшот, не живая
     $product->price), снизу сама сумма строки (CartItem::amount(), тот же
     снапшот × quantity — тот же источник, что и у общего итога,
     CartManager::amount() в cart-summary.blade.php, иначе сумма строк и итог
     могли бы разойтись при смене цены товара после добавления в корзину).

     Крестик — отдельная маленькая Alpine-обвязка (uiCartRemove,
     resources/js/cart.js), не часть степпера: убирает товар целиком
     независимо от текущего количества. И крестик, и степпер (при уходе в 0)
     шлют одно и то же window-событие cart:item-removed — обёртка ниже просто
     слушает оба источника и скрывает себя.

     Ниже sm: — двухрядная сетка (превью занимает обе строки, имя+степпер+
     сумма+крестик раскладываются по col-start/row-start), с sm: — единый
     ряд из 5 колонок: узкое превью 56px иначе сжимает название почти
     в ничто на мобильном. h-[34px] на степпере — .qty button{height:34px}
     из брендбука (design-system.html §10); без явной высоты корневой div
     степпера в grid-ячейке садится по контенту и «плющится».

     Количество и сумма — реактивные значения здесь: приходят с сервера как
     снапшот на первый рендер (`x-data`), но при +/− в степпере
     (cart-stepper.blade.php, свой обособленный x-data) должны обновиться без
     перезагрузки. Степпер и эта обёртка — разные Alpine-scope, поэтому
     напрямую состоянием не делятся: uiCartStepper.send() (resources/js/cart.js)
     шлёт window-событие cart:item-updated, по тому же образцу, что и
     cart:item-removed для скрытия строки при уходе в 0. --}}
@props(['cartItem'])

@php
    $product = $cartItem->product;
    $beerStyleName = $product->beerDetails?->beerStyle?->name;
    $available = $product->isAvailable();

    $spec = collect([
        $beerStyleName,
        $product->volume?->label,
        $product->beerDetails?->abv ? spec_number($product->beerDetails->abv).'%' : null,
    ])->filter();
@endphp

<div
    x-data="{ removed: false, quantity: {{ $cartItem->quantity }}, unitPrice: @js((string) $cartItem->price), amount: @js((string) $cartItem->amount) }"
    x-show="! removed"
    x-on:cart:item-removed.window="if ($event.detail.productId === {{ $product->id }}) removed = true"
    x-on:cart:item-updated.window="if ($event.detail.productId === {{ $product->id }}) { quantity = $event.detail.quantity; amount = $event.detail.amount }"
    class="grid grid-cols-[56px_1fr_auto] items-center gap-x-4 gap-y-3 border-b border-hairline py-3.5 last:border-b-0 sm:grid-cols-[56px_1fr_auto_auto_auto] sm:gap-y-0"
>
    <div @class(['row-span-2 h-14 w-14 shrink-0 overflow-hidden rounded-sm border border-hairline bg-cream-100 sm:row-span-1', 'grayscale opacity-50' => ! $available])>
        {{-- product-card-thumb (app.css) — тот же ховер-хатчинг, что и у
             картинки карточки каталога (product-card.blade.php), класс не
             привязан к размеру карточки. --}}
        <a href="{{ route('product.show', $product) }}" aria-label="{{ __('catalog.go_to_product') }}" class="product-card-thumb block h-full w-full">
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
        </a>
    </div>

    <div @class(['col-start-2 row-start-1 min-w-0', 'opacity-60' => ! $available])>
        @if ($product->manufacturer)
            <div class="truncate font-mono text-badge uppercase text-ink-300">{{ $product->manufacturer->name }}</div>
        @endif
        <div class="truncate font-display text-btn-lg font-bold uppercase text-ink-900"><a href="{{ route('product.show', $product) }}" class="hover:underline">{{ $product->brand ?: $product->name }}</a></div>
        @if ($spec->isNotEmpty())
            <div class="truncate text-caption text-ink-500">{{ $spec->implode(' · ') }}</div>
        @endif
    </div>

    @if (! $available)
        {{-- Товар выбыл из наличия после того, как уже был добавлен в
             корзину (или сток обнулился задним числом) — вместо степпера
             (управлять количеством нечего) чип «нет в наличии», в той же
             ячейке грида, что и cart-stepper ниже. Удаление — крестик
             справа (uiCartRemove), тот же, что и для доступных строк. --}}
        <div class="col-start-2 row-start-2 flex h-[34px] items-center sm:col-start-3 sm:row-start-1">
            <x-ui.chip tone="rust">{{ __('catalog.out_of_stock') }}</x-ui.chip>
        </div>
    @else
        <x-ui.cart-stepper
            :product="$product"
            :quantity="$cartItem->quantity"
            :show-buy-button="false"
            class="col-start-2 row-start-2 h-[34px] sm:col-start-3 sm:row-start-1"
        />
    @endif

    <div class="col-start-3 row-start-2 justify-self-end text-right sm:col-start-4 sm:row-start-1 sm:w-24">
        <div class="whitespace-nowrap font-mono text-micro text-ink-500" x-text="quantity + ' × ' + unitPrice"></div>
        <div class="whitespace-nowrap font-mono text-body-m text-ink-900" x-text="amount"></div>
    </div>

    <form
        method="POST"
        action="{{ route('cart.destroy', $product) }}"
        x-data="uiCartRemove({{ $product->id }})"
        x-on:submit.prevent="send($el)"
        class="col-start-3 row-start-1 justify-self-end sm:col-start-5 sm:row-start-1"
    >
        @csrf
        @method('DELETE')
        <button
            type="submit"
            aria-label="{{ __('account.cart.remove') }}"
            class="grid h-8 w-8 place-items-center text-ink-300 transition hover:text-rust"
        ><x-ui.icon name="x" :size="16" /></button>
    </form>
</div>
