{{-- Оформление заказа (/checkout) — не часть личного кабинета
     (App\Http\Controllers\OrderController, тот же принцип, что /cart), но
     переиспользует ту же 8/4-сетку и тёмную teal-панель итога, что
     pages/cart.blade.php/cart-summary.blade.php — только с формой вместо
     степпера/крестика (тут ничего не меняется в самой корзине).

     Способ доставки/оплаты клиенту не выбрать — фиксированы сервером
     («Служба доставки»/«При получении», config/order.php,
     OrderController::store()) и на странице не показываются.

     mode="ajax" — общий паттерн форм проекта (CLAUDE.md, "Private account
     area"), 422-ошибка бизнес-правил пайплайна (мин. сумма/нет в наличии)
     приходит от OrderController::store() как {errors:{checkout:[...]}} —
     искусственное поле 'checkout' для <x-ui.error name="checkout">. --}}
@php
    $defaultAddress = $addresses->firstWhere('is_default', true) ?? $addresses->first();
    $selectedAddressId = old('address_id', $defaultAddress?->id);
@endphp

<x-layouts.site :title="__('order.title')">
    <div class="mx-auto max-w-page px-6 py-10">
        <h1 class="mb-6 font-display text-heading-m uppercase text-ink-900">{{ __('order.title') }}</h1>

        <x-ui.form :action="route('checkout.store')" method="POST" mode="ajax">
            <x-ui.error name="checkout" class="mb-4 block" />

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
                <div class="grid gap-6 lg:col-span-8">
                    <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-6">
                        <h2 class="mb-4 font-display text-heading-s uppercase text-ink-900">{{ __('order.summary') }}</h2>
                        <div class="grid gap-3">
                            @foreach ($cartItems as $cartItem)
                                <div class="flex items-center justify-between border-b border-hairline pb-3 last:border-b-0 last:pb-0">
                                    <div class="min-w-0">
                                        <div class="truncate text-body-m text-ink-900">{{ $cartItem->product->brand ?: $cartItem->product->name }}</div>
                                        <div class="text-caption text-ink-500">{{ $cartItem->quantity }} &times; {{ $cartItem->price }}</div>
                                    </div>
                                    <span class="font-mono text-body-m text-ink-900">{{ $cartItem->amount }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.surface>

                    <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="font-display text-heading-s uppercase text-ink-900">{{ __('order.address') }}</h2>
                            <x-ui.link :href="route('account.addresses.create')">{{ __('order.address_add') }}</x-ui.link>
                        </div>

                        @if ($addresses->isEmpty())
                            <p class="text-body-m text-ink-500">{{ __('order.address_none') }}</p>
                        @else
                            <div class="grid gap-3">
                                @foreach ($addresses as $address)
                                    <x-ui.radio
                                        name="address_id"
                                        :value="$address->id"
                                        :checked="(string) $selectedAddressId === (string) $address->id"
                                        class="items-start rounded-sm border border-hairline p-3.5"
                                    >
                                        {{ $address->city }}, {{ $address->street }}, {{ $address->house }}
                                        @if ($address->apartment)
                                            &middot; {{ __('account.address.apartment') }} {{ $address->apartment }}
                                        @endif
                                    </x-ui.radio>
                                @endforeach
                            </div>
                        @endif
                        <x-ui.error name="address_id" class="mt-2" />
                    </x-ui.surface>

                    <x-ui.surface tone="paper-2" class="rounded-sm border border-hairline p-6">
                        <h2 class="mb-4 font-display text-heading-s uppercase text-ink-900">{{ __('account.profile.details_title') }}</h2>
                        <div class="grid gap-3.5 sm:grid-cols-2">
                            <x-ui.input-field name="first_name" :label="__('account.field.first_name')" :value="old('first_name', $profile?->first_name)" required />
                            <x-ui.input-field name="last_name" :label="__('account.field.last_name')" :value="old('last_name', $profile?->last_name)" required />
                        </div>
                        <x-ui.input-field name="phone" :label="__('account.field.phone')" :value="old('phone', $profile?->phone)" required class="mt-3.5" />
                        <x-ui.textarea-field name="comment" :label="__('order.comment')" :value="old('comment')" class="mt-3.5" />
                    </x-ui.surface>
                </div>

                <div class="lg:sticky lg:top-6 lg:col-span-4 lg:self-start">
                    <div class="rounded-sm bg-teal-900 p-6 text-cream-100">
                        <div class="flex items-center justify-between text-body-m text-cream-100/75">
                            <span>{{ __('account.cart.items') }}</span>
                            <span class="font-mono">{{ $count }}</span>
                        </div>

                        <div class="mt-3 flex items-center justify-between border-t border-teal-800 pt-3 font-display text-heading-s font-semibold uppercase">
                            <span>{{ __('account.cart.total_label') }}</span>
                            <span class="font-mono">{{ $amount }}</span>
                        </div>

                        <x-ui.btn type="submit" variant="cream" size="lg" block class="mt-5" x-bind:disabled="submitting">
                            <span x-show="!submitting">{{ __('order.submit') }}</span>
                            <span x-show="submitting" x-cloak class="inline-flex items-center">
                                <x-ui.spinner size="16" class="mr-2" />{{ __('order.submitting') }}
                            </span>
                        </x-ui.btn>
                    </div>
                </div>
            </div>
        </x-ui.form>
    </div>
</x-layouts.site>
