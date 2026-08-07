{{-- Тёмная панель итога (/cart, pages/cart.blade.php) — воспроизводит
     .summary из брендбука (design-system.html §10). Брендбук показывает там
     же строки «Доставка»/«Промокод» и кнопку «Оформить заказ» — в проекте
     нет ни промокодов, ни Order/checkout, поэтому здесь только реальные
     данные: одна строка «Итого (N товаров)» с суммой из CartManager::amount().
     Та же логика, что уже применена в <x-ui.account-nav> — не стабить то,
     чего нет (CLAUDE.md). x-text перекрывает серверное число сразу после
     гидратации Alpine — сумма держит $store.cart.amount (resources/js/cart.js),
     общий со степпером/крестиком каждой строки, поэтому любое изменение
     корзины отражается тут без перезагрузки. --}}
@props(['count', 'amount'])

<div class="rounded-sm bg-teal-900 p-6 text-cream-100">
    <div class="flex items-center justify-between font-display text-heading-s font-semibold uppercase">
        <span>{{ __('account.cart.total', ['count' => $count]) }}</span>
        <span class="font-mono" x-text="$store.cart.amount">{{ $amount }}</span>
    </div>
</div>
