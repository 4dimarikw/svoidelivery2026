{{-- Строка корзины (/cart, pages/cart.blade.php) — воспроизводит .cart-line
     из брендбука (design-system.html §10 «Корзина и оформление»): превью
     56×56 → название+спека → степпер → цена строки → крестик-удаление,
     через CSS Grid (не flex — grid `gap` безопасен для Safari >= 13.1, флекс-
     gap запрещён CLAUDE.md только для Safari < 14.1).

     Цена строки — CartItem::amount() (снятый снапшот цены × quantity), не
     живая $product->price — тот же источник, что и у общего итога
     (CartManager::amount() в cart-summary.blade.php), иначе сумма строк и
     итог могли бы разойтись при смене цены товара после добавления в корзину.

     Крестик — отдельная маленькая Alpine-обвязка (uiCartRemove,
     resources/js/cart.js), не часть степпера: убирает товар целиком
     независимо от текущего количества. И крестик, и степпер (при уходе в 0)
     шлют одно и то же window-событие cart:item-removed — обёртка ниже просто
     слушает оба источника и скрывает себя.

     Ниже sm: — двухрядная сетка (превью занимает обе строки, имя+степпер+
     цена+крестик раскладываются по col-start/row-start), с sm: — прежний
     единый ряд из 5 колонок: узкое превью 56px иначе сжимает название почти
     в ничто на мобильном. h-[34px] на степпере — .qty button{height:34px}
     из брендбука (design-system.html §10); без явной высоты корневой div
     степпера в grid-ячейке садится по контенту и «плющится». --}}
@props(['cartItem'])

@php
    $product = $cartItem->product;

    $spec = collect([
        $product->volume?->label,
        $product->beerDetails?->abv ? spec_number($product->beerDetails->abv).'%' : null,
    ])->filter();
@endphp

<div
    x-data="{ removed: false }"
    x-show="! removed"
    x-on:cart:item-removed.window="if ($event.detail.productId === {{ $product->id }}) removed = true"
    class="grid grid-cols-[56px_1fr_auto] items-center gap-x-4 gap-y-3 border-b border-hairline py-3.5 last:border-b-0 sm:grid-cols-[56px_1fr_auto_auto_auto] sm:gap-y-0"
>
    <div class="row-span-2 h-14 w-14 shrink-0 overflow-hidden rounded-sm border border-hairline bg-cream-100 sm:row-span-1">
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

    <div class="col-start-2 row-start-1 min-w-0">
        <div class="truncate font-display text-btn-lg font-bold uppercase text-ink-900">{{ $product->brand ?: $product->name }}</div>
        @if ($spec->isNotEmpty())
            <div class="truncate text-caption text-ink-500">{{ $spec->implode(' · ') }}</div>
        @endif
    </div>

    <x-ui.cart-stepper
        :product="$product"
        :quantity="$cartItem->quantity"
        :show-buy-button="false"
        class="col-start-2 row-start-2 h-[34px] sm:col-start-3 sm:row-start-1"
    />

    <span class="col-start-3 row-start-2 justify-self-end whitespace-nowrap font-mono text-body-m text-ink-900 sm:col-start-4 sm:row-start-1 sm:w-24 sm:text-right">{{ $cartItem->amount }}</span>

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
