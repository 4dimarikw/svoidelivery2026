{{-- Тёмная панель итога (/cart, pages/cart.blade.php) — воспроизводит
     .summary из брендбука (design-system.html §10). Брендбук показывает там
     же строки «Доставка»/«Промокод» — промокодов в проекте нет, поэтому
     здесь только реальные данные: «Товары»/«Итого». Две кнопки: основная
     «Оформить заказ» ведёт на /checkout (App\Http\Controllers\OrderController,
     см. checkout.blade.php), вторичная «Продолжить покупки» — на home,
     понижена до ghost-light, чтобы не спорить с основным CTA за внимание.

     Обе строки x-text перекрывают серверное число сразу после гидратации
     Alpine — держат $store.cart.count/$store.cart.amount (resources/js/cart.js),
     общие со степпером/крестиком каждой строки, поэтому любое изменение
     корзины отражается тут без перезагрузки. Count вынесен из переводной
     строки (было account.cart.total = "Итого (:count товаров)") в отдельный
     <span> именно ради этого — число внутри __() нельзя привязать к x-text. --}}
@props(['count', 'amount'])

<div class="rounded-sm bg-teal-900 p-6 text-cream-100">
    <div class="flex items-center justify-between text-body-m text-cream-100/75">
        <span>{{ __('account.cart.items') }}</span>
        <span class="font-mono" x-text="$store.cart.count">{{ $count }}</span>
    </div>

    <div class="mt-3 flex items-center justify-between border-t border-teal-800 pt-3 font-display text-heading-s font-semibold uppercase">
        <span>{{ __('account.cart.total_label') }}</span>
        <span class="font-mono" x-text="$store.cart.amount">{{ $amount }}</span>
    </div>

    <x-ui.btn :href="route('checkout.index')" variant="cream" size="lg" block class="mt-5">
        {{ __('account.cart.checkout') }}
    </x-ui.btn>

    <x-ui.btn :href="route('home')" variant="ghost-light" size="lg" block class="mt-3">
        {{ __('account.cart.continue') }}
    </x-ui.btn>
</div>
