{{-- Степпер количества (Domain\Cart, CLAUDE.md), воспроизводит .qty из
     брендбука (design-system.html §06/§10): при qty=0 и $showBuyButton — видна
     обычная кнопка «Купить»/задизейбленное «Сообщить», при qty>0 — блок
     −/N/+; минус на количестве 1 убирает товар и возвращает «Купить»
     ($showBuyButton) либо просто убирает строку (cart-line.blade.php,
     $showBuyButton=false — на странице корзины товар уже в ней, состояния
     «Купить» не существует, форма increase — чистый submit-target для «+»,
     без собственной видимой кнопки).

     Количество приходит снаружи ($quantity) — либо CartManager::quantityOf()
     (карточка каталога/избранного), либо уже загруженный CartItem::quantity
     (строка корзины, без лишнего запроса — CartManager всё равно мемоизирован
     на тот же результат). «Купить» и «+» ведут на один и тот же маршрут
     cart.increase (кнопка «+» физически внутри формы decrease, но ссылается
     на форму increase через HTML5-атрибут form="…" — экономит отдельный
     маршрут на "уже есть, но нужно ещё"), «−» — на cart.decrease. Обе формы
     делят один x-data (uiCartStepper, resources/js/cart.js), тот же
     fetch+JSON+фолбэк-на-submit приём, что и у избранного.

     Компакт < xl (карточка каталога на 3/4/5-колоночной сетке — см.
     product-card.blade.php): кнопки −/+ и цифра уже 26px вместо
     брендбучных 34px, «Купить» — px-2 вместо .btn'ового 22px. .btn — класс
     из @layer components (app.css), а px-*/w-*-утилиты лежат в @layer
     utilities, который в app.css идёт следующим директивом — при равной
     специфичности они и так побеждают .btn без !important. Тот же
     компонент используется в cart-line.blade.php (/cart) — компакт заодно
     улучшает и её на мобиле. --}}
@props(['product', 'quantity', 'showBuyButton' => true])

@php
    $increaseId = 'cart-increase-'.$product->id;
@endphp

<div x-data="uiCartStepper({{ $quantity }}, {{ $product->id }})" {{ $attributes }}>
    <form
        id="{{ $increaseId }}"
        method="POST"
        action="{{ route('cart.increase', $product) }}"
        @if ($showBuyButton) x-show="quantity === 0" class="h-full" @else class="hidden" @endif
        x-on:submit.prevent="send($el)"
    >
        @csrf
        @if ($showBuyButton)
            <x-ui.btn type="submit" variant="primary" size="md" block class="h-full px-2 xl:px-[22px]" :disabled="! $product->in_stock">
                {{ $product->in_stock ? __('catalog.buy') : __('catalog.notify') }}
            </x-ui.btn>
        @endif
    </form>

    <form
        method="POST"
        action="{{ route('cart.decrease', $product) }}"
        @if ($showBuyButton) x-show="quantity > 0" x-cloak @endif
        x-on:submit.prevent="send($el)"
        class="flex h-full items-stretch overflow-hidden rounded-sm border border-hairline"
    >
        @csrf
        @method('PATCH')
        <button
            type="submit"
            aria-label="{{ __('catalog.cart.decrease') }}"
            class="grid w-[26px] shrink-0 place-items-center bg-cream-200 text-ink-900 transition hover:bg-cream-300 xl:w-[34px]"
        >−
        </button>
        {{-- min-w-[40px] — .qty span{width:40px} из брендбука (§10), возвращается
             только от xl (min-w-[34px] xl: — фактическая колонка 34px, не 40,
             см. кнопки выше). flex-1 сам по себе имеет flex-basis:0 — там, где
             форме нечего раздавать (grid-колонка auto в cart-line.blade.php),
             ячейка схлопывается до ширины цифры; min-width держит нужную
             ширину и там, и там, а flex-1 всё равно дотягивает ячейку шире на
             карточке каталога (.card .actions .qty span{flex:1}), где строка
             растянута. --}}
        <span class="grid min-w-[26px] flex-1 place-items-center font-mono text-caption xl:min-w-[34px]" x-text="quantity"></span>
        <button
            type="submit"
            form="{{ $increaseId }}"
            aria-label="{{ __('catalog.cart.increase') }}"
            :disabled="quantity >= {{ (int) $product->stock_quantity }}"
            class="grid w-[26px] shrink-0 place-items-center bg-cream-200 text-ink-900 transition hover:bg-cream-300 disabled:cursor-not-allowed disabled:opacity-50 xl:w-[34px]"
        >+
        </button>
    </form>
</div>
