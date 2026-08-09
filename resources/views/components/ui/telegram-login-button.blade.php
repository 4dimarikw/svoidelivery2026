{{-- Telegram Login Widget.

     Кнопку рисует сам Telegram внутри iframe — стилизовать её нашими токенами
     нельзя, доступны только data-size / data-radius / data-userpic / data-lang.
     data-radius="3" совпадает с borderRadius.md из tailwind.config.js.

     После успеха Telegram уводит браузер на data-auth-url обычным GET —
     это всегда полная перезагрузка, ajax-режим тут недостижим.

     Виджет работает только на домене, прописанном боту через BotFather
     /setdomain (localhost Telegram не принимает). Без заведённого бота
     ничего не рендерим, чтобы на локали не висела мёртвая кнопка. --}}

@props([
    'authUrl' => null,
])

@php
    // Не config('services.telegram.bot') — тот заполняется лениво, только
    // внутри TelegramLoginController::callback(), и на рендере страницы
    // входа ещё не будет заполнен. Источник правды — сам бот в БД.
    $bot = \Domain\Telegram\Models\TelegramBot::current();
@endphp

@if ($bot?->username)
    <div {{ $attributes->class('flex justify-center') }}>
        <script
            async
            src="https://telegram.org/js/telegram-widget.js?22"
            data-telegram-login="{{ $bot->username }}"
            data-size="large"
            data-radius="3"
            data-userpic="false"
            data-lang="{{ app()->getLocale() }}"
            data-auth-url="{{ $authUrl ?? route('auth.telegram.callback') }}"
            data-request-access="write"
        ></script>
    </div>
@endif
