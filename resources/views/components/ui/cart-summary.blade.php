{{-- Тёмная панель итога (/cart, pages/cart.blade.php) — воспроизводит
     .summary из брендбука (design-system.html §10). Брендбук показывает там
     же строки «Доставка»/«Промокод» — промокодов в проекте нет, поэтому
     здесь только реальные данные: «Товары»/«Итого».

     Кнопки — не часть компонента, а $slot: оформление больше не отдельная
     страница (/checkout удалён), форма с полями встроена в саму
     pages/cart.blade.php, и её submit-кнопка (form="checkout-form", вне
     самой <form> — см. комментарий там) — вызывающая сторона, единственная,
     кто знает про id этой формы. Единственный потребитель компонента —
     pages/cart.blade.php.

     Обе строки x-text перекрывают серверное число сразу после гидратации
     Alpine — держат $store.cart.count/$store.cart.amount (resources/js/cart.js),
     общие со степпером/крестиком каждой строки, поэтому любое изменение
     корзины отражается тут без перезагрузки. Count — в отдельном <span>,
     не внутри переводной строки: число внутри __() нельзя привязать к x-text. --}}
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

    {{ $slot }}
</div>
