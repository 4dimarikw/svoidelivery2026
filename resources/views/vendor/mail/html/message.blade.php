<x-mail::layout>
    {{-- Шапка. --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')"></x-mail::header>
    </x-slot:header>

    {{-- Основное содержимое. --}}
    {!! $slot !!}

    {{-- Дополнительный текст. --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {!! $subcopy !!}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Подвал. --}}
    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
