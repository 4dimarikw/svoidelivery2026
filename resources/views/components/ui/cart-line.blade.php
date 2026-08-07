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
     слушает оба источника и скрывает себя. --}}
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
    class="grid grid-cols-[56px_1fr_auto_auto_auto] items-center gap-4 border-b border-hairline py-3.5"
>
    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-sm border border-hairline bg-cream-100">
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

    <div class="min-w-0">
        <div class="truncate font-display text-btn-lg font-bold uppercase text-ink-900">{{ $product->brand ?: $product->name }}</div>
        @if ($spec->isNotEmpty())
            <div class="truncate text-caption text-ink-500">{{ $spec->implode(' · ') }}</div>
        @endif
    </div>

    <x-ui.cart-stepper :product="$product" :quantity="$cartItem->quantity" :show-buy-button="false" />

    <span class="whitespace-nowrap font-mono text-body-m text-ink-900">{{ $cartItem->amount }}</span>

    <form
        method="POST"
        action="{{ route('cart.destroy', $product) }}"
        x-data="uiCartRemove({{ $product->id }})"
        x-on:submit.prevent="send($el)"
    >
        @csrf
        @method('DELETE')
        <button
            type="submit"
            aria-label="{{ __('account.cart.remove') }}"
            class="grid h-7 w-7 place-items-center text-ink-300 transition hover:text-rust"
        >✕</button>
    </form>
</div>
