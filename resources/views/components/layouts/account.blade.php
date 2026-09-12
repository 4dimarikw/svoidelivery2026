{{-- Макет личного кабинета с шапкой, подвалом и боковой навигацией. Сетка из 12 колонок становится одноколоночной
   ниже lg. --}}
@props([
    'title' => null,
    'active' => null, // 'profile' | 'orders' | 'addresses'
])
<x-layouts.site :title="$title">
    <div class="mx-auto grid max-w-page grid-cols-1 gap-6 px-6 py-12 lg:grid-cols-12">
        <div class="lg:col-span-3">
            <x-ui.account-nav :active="$active" />
        </div>

        <div class="min-w-0 lg:col-span-9">
            {{ $slot }}
        </div>
    </div>
</x-layouts.site>
