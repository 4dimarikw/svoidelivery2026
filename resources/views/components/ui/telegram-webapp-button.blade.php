{{-- Вход через Telegram Mini App. Пара к <x-ui.telegram-login-button> —
     видна только когда сайт открыт внутри Telegram Web App
     (auth/login.blade.php переключает их через Alpine, telegramAuth,
     resources/js/telegram.js). Обычная (не ajax) POST-форма: успех — это
     редирект с установленной сессией, ровно как у виджета.

     init_data заполняется из window.Telegram.WebApp.initData самим Alpine
     перед отправкой — здесь пусто до init(). --}}

@props([
    'action' => null,
])

<form
    method="POST"
    action="{{ $action ?? route('auth.telegram.webapp') }}"
    {{ $attributes->class('flex justify-center') }}
>
    @csrf
    <input type="hidden" name="init_data" x-ref="initData">
    <x-ui.btn type="submit" size="lg" block>{{ __('account.telegram.webapp_login') }}</x-ui.btn>
</form>
