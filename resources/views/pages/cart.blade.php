{{-- Корзина — самостоятельная страница (/cart), не часть личного кабинета:
     <x-layouts.site>, не <x-layouts.account> — без сайдбара профиля/адресов/
     избранного. Товары строками (cart-line.blade.php) в светлой карточке
     (surface), не голым списком — воспроизводит раздел «10 — Корзина и
     оформление» брендбука (design-system.html:1100-1141): превью/название/
     степпер/цена строки/крестик слева, итог в тёмной teal-панели справа
     (cart-summary.blade.php), прилипающей при скролле длинного списка.

     Отдельной страницы /checkout больше нет — оформление (адрес/ФИО/телефон/
     комментарий) встроено сюда же, под строками корзины, тем же
     App\Http\Controllers\OrderController::store() (POST checkout.store).
     Форма оформления НЕ оборачивает строки корзины: каждая строка содержит
     собственные <form> (cart-line.blade.php — крестик, cart-stepper.blade.php
     — +/−, оба no-JS фолбэк), а вложенные <form> невалидны и браузер их
     молча ломает. Поэтому форма — отдельный блок ниже строк, а submit-кнопка
     вообще физически лежит в sticky-панели итога (cart-summary.blade.php,
     $slot), вне самой формы, и привязана к ней HTML5-атрибутом
     form="checkout-form" — тот же приём, что уже использует кнопка «+» в
     cart-stepper.blade.php (форма increase ссылается через `form=`, а не
     вложена). Поскольку кнопка вне Alpine-scope формы (uiForm.submitting —
     x-data самой <form>), форма зеркалит submitting в Alpine.store('checkout')
     через x-effect (resources/js/cart.js), кнопка читает оттуда.

     $cartItems — сами CartItem с eager-loaded product.* (CartController::index()),
     не голые Product: цена строки нужна из CartItem::amount() (снятый снапшот),
     не живой $product->price — см. комментарий в cart-line.blade.php.
     $addresses/$profile — тоже из CartController::index(), для формы ниже.

     x-show на списке/пустом состоянии/кнопке очистки — поверх серверного
     @if ($cartItems->isEmpty()), не вместо него: первый рендер и без JS
     остаётся корректным по $cartItems, а $store.cart.count (уже сидируется
     ниже через x-init) доигрывает сценарий "удалили крестиком последний
     товар" без перезагрузки — иначе после такого удаления на странице
     зависали бы пустая сетка и панель итога с нулями. Форма оформления
     скрыта тем же условием — оформлять пустую корзину нечем. --}}
@php
    // no-JS фолбэк: increase/decrease/destroy/clear на этой же странице делают
    // back()/redirect() с session('status'), как и в account/favorites/index.blade.php.
    $noJsStatusMessage = match (session('status')) {
        'cart-item-added' => __('account.cart.added'),
        'cart-item-removed' => __('account.cart.removed'),
        'cart-cleared' => __('account.cart.cleared'),
        'cart-item-unavailable' => __('account.cart.unavailable'),
        default => null,
    };

    $defaultAddress = $addresses->firstWhere('is_default', true) ?? $addresses->first();
    $selectedAddressId = old('address_id', $defaultAddress?->id);
    // Комментарий уже выбранного адреса (например «домофон 45») — стартовое
    // значение поля «Комментарий к заказу»; при переключении радио-кнопки
    // ниже подменяется на лету через Alpine (addressComments).
    $selectedAddress = $addresses->firstWhere('id', (int) $selectedAddressId);

    // Товары, выбывшие из наличия уже после добавления в корзину — плашка
    // над кнопкой оформления (см. ниже) и приглушение строки (cart-line.blade.php).
    // Кнопку «Оформить заказ» это не блокирует — единственный надёжный барьер
    // всё равно Domain\Order\Processes\CheckProductInStock на бэке, сток
    // может кончиться и после этого рендера.
    $unavailableProductIds = $cartItems
        ->reject(fn ($item) => $item->product->isAvailable())
        ->pluck('product_id')
        ->values()
        ->all();
@endphp

<x-layouts.site :title="__('account.cart.title')">
    <div class="mx-auto max-w-page px-6 py-10" x-init="$store.cart.amount = @js((string) $amount)">
        @if ($noJsStatusMessage)
            <x-ui.alert tone="ok" class="mb-6">{{ $noJsStatusMessage }}</x-ui.alert>
        @endif

        <h1 class="mb-6 font-display text-heading-m uppercase text-ink-900">{{ __('account.cart.title') }}</h1>

        <div x-show="$store.cart.count === 0" @if ($cartItems->isNotEmpty()) x-cloak @endif>
            <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-8 text-center">
                <p class="text-body-m text-ink-500">{{ __('account.cart.empty') }}</p>
            </x-ui.surface>
        </div>

        {{-- 8/4 — та же 12-колоночная сетка, что в pages/home.blade.php
             (grid, не flex — CLAUDE.md запрещает только flex-gap). --}}
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-12" x-show="$store.cart.count > 0" @if ($cartItems->isEmpty()) x-cloak @endif>
            <div class="grid gap-8 lg:col-span-8">
                @if ($cartItems->isNotEmpty())
                    <div class="flex justify-end">
                        <form method="POST" action="{{ route('cart.clear') }}" onsubmit="return confirm(@js(__('account.cart.clear_confirm')))" x-show="$store.cart.count > 0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-caption text-rust hover:underline">{{ __('account.cart.clear') }}</button>
                        </form>
                    </div>
                @endif

                <x-ui.surface tone="paper-2" class="border border-hairline px-5">
                    @foreach ($cartItems as $cartItem)
                        <x-ui.cart-line :cart-item="$cartItem" />
                    @endforeach
                </x-ui.surface>

                <x-ui.form
                    id="checkout-form"
                    :action="route('checkout.store')"
                    method="POST"
                    mode="ajax"
                    :state="['comment' => old('comment', $selectedAddress?->comment)]"
                    x-effect="$store.checkout.submitting = submitting; $store.checkout.error = errorFor('checkout')"
                    class="grid gap-8"
                >
                    <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="font-display text-heading-s uppercase text-ink-900">{{ __('order.address') }}</h2>
                            <x-ui.link :href="route('account.addresses.create')">{{ __('order.address_add') }}</x-ui.link>
                        </div>

                        @if ($addresses->isEmpty())
                            <p class="text-body-m text-ink-500">{{ __('order.address_none') }}</p>
                        @else
                            {{-- addressComments — карта id адреса → его комментарий
                                 (например «домофон 45»), один раз на весь список;
                                 x-on:change ниже подставляет его в общий
                                 state.comment формы (уходит выше по цепочке Alpine-
                                 scope до <x-ui.form>), полностью заменяя то, что
                                 было в поле — так решил продукт: без защиты от
                                 затирания ручного ввода. --}}
                            <div class="grid gap-3" x-data="{ addressComments: @js($addresses->pluck('comment', 'id')) }">
                                @foreach ($addresses as $address)
                                    <x-ui.radio
                                        name="address_id"
                                        :value="$address->id"
                                        :checked="(string) $selectedAddressId === (string) $address->id"
                                        x-on:change="state.comment = addressComments[$el.value] ?? ''"
                                        class="items-start rounded-sm border border-hairline p-3.5"
                                    >
                                        {{ $address->city }}, {{ $address->address }}
                                    </x-ui.radio>
                                @endforeach
                            </div>
                        @endif
                        <x-ui.error name="address_id" class="mt-2" />
                    </x-ui.surface>

                    <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-6">
                        <h2 class="mb-4 font-display text-heading-s uppercase text-ink-900">{{ __('account.profile.details_title') }}</h2>
                        {{-- Один внешний grid gap-3.5 на все поля — тот же паттерн,
                             что account/profile.blade.php. Точечный class="mt-3.5"
                             на *-field здесь не годится: *-field форвардит $attributes
                             в сам контрол (input/textarea), а не в обёртку
                             <x-ui.field>, так что margin-top оседал бы ВНУТРИ поля
                             (между лейблом и инпутом), а не между полями. --}}
                        <div class="grid gap-3.5">
                            <div class="grid gap-3.5 sm:grid-cols-2">
                                <x-ui.input-field name="first_name" :label="__('account.field.first_name')" :value="old('first_name', $profile?->first_name)" required />
                                <x-ui.input-field name="last_name" :label="__('account.field.last_name')" :value="old('last_name', $profile?->last_name)" required />
                            </div>
                            <x-ui.input-field
                                name="phone"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                                x-data="phoneMask"
                                placeholder="+7 (999) 123-45-67"
                                :label="__('account.field.phone')"
                                :help="__('account.field.phone_help')"
                                :value="old('phone', $profile?->phone)"
                                required
                            />
                            <x-ui.textarea-field
                                name="comment"
                                :label="__('order.comment')"
                                :value="old('comment', $selectedAddress?->comment)"
                                x-model="state.comment"
                            />
                        </div>
                    </x-ui.surface>
                </x-ui.form>
            </div>

            {{-- lg:top-24, не lg:top-6 — та же причина, что у панели
                 фильтров каталога (см. pages/catalog/_filters.blade.php):
                 sticky-шапка занимает ~73px сверху, top-6 залипал бы под ней. --}}
            <div class="lg:sticky lg:top-24 lg:col-span-4 lg:self-start">
                {{-- Предупреждение о недоступных товарах в корзине — то же
                     window-событие cart:item-removed, что и у строк/степпера,
                     чтобы плашка исчезала сама при удалении последнего такого
                     товара, без перезагрузки страницы. --}}
                <div
                    x-data="{ unavailable: {{ count($unavailableProductIds) }} }"
                    x-on:cart:item-removed.window="if (@js($unavailableProductIds).includes($event.detail.productId)) unavailable--"
                    x-show="unavailable > 0"
                    @if ($unavailableProductIds === []) style="display: none" @endif
                    class="mb-4"
                >
                    <x-ui.alert tone="warn">{{ __('account.cart.has_unavailable') }}</x-ui.alert>
                </div>

                <x-ui.cart-summary :count="$cart->count()" :amount="$amount">
                    <x-ui.btn type="submit" form="checkout-form" variant="cream" size="lg" block class="mt-5" x-bind:disabled="$store.checkout.submitting">
                        <span x-show="! $store.checkout.submitting">{{ __('order.submit') }}</span>
                        <span x-show="$store.checkout.submitting" x-cloak class="inline-flex items-center">
                            <x-ui.spinner size="16" class="mr-2" />{{ __('order.submitting') }}
                        </span>
                    </x-ui.btn>

                    <x-ui.btn :href="route('home')" variant="ghost-light" size="lg" block class="mt-3">
                        {{ __('account.cart.continue') }}
                    </x-ui.btn>
                </x-ui.cart-summary>

                @php
                    $checkoutError = $errors->first('checkout');
                @endphp
                {{-- Не <x-ui.error-alert> — тот читает errorFor() из
                     Alpine-scope ближайшего x-data="uiForm(...)" предка по
                     ДОМ-дереву, а этот блок — сосед #checkout-form (в
                     сайдбаре), не потомок, с тех пор как submit переехал в
                     cart-summary. mode="ajax" одним пропом это не чинит:
                     он только заставляет Blade сгенерировать x-show/x-text
                     с вызовом errorFor(), а вызвать его здесь всё равно
                     не из чего — метод физически не виден вне <form>,
                     обращение к нему кидает ReferenceError в консоль, и
                     плашка так и не появляется. Вместо errorFor() читаем
                     $store.checkout.error, куда x-effect на самой форме
                     зеркалит то же значение (resources/js/cart.js) — тот
                     же приём, что уже применён для submitting/спиннера
                     кнопки выше. --}}
                <div
                    x-show="$store.checkout.error"
                    @if (! $checkoutError) style="display: none" @endif
                    class="mt-4"
                >
                    <x-ui.alert tone="err">
                        <span x-text="$store.checkout.error">{{ $checkoutError }}</span>
                    </x-ui.alert>
                </div>
            </div>
        </div>
    </div>
</x-layouts.site>
