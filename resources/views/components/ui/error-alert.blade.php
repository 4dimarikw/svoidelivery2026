{{-- То же, что <x-ui.error> (components/ui/error.blade.php — приоритет
     Alpine → $errors → ничего, mode единственный @aware-ключ библиотеки,
     см. CLAUDE.md), но для ошибки, которая относится ко всей форме, а не
     к конкретному инпуту (искусственное имя поля вроде 'checkout') —
     рендерит заметный <x-ui.alert tone="err">, а не микро-текст под
     полем. --}}
@props([
    'name',
    'bag' => 'default',
    'tone' => 'err',
    'title' => null,
])
@aware(['mode' => 'default'])

@php
    $message = $errors->getBag($bag)->first($name);
@endphp

@if ($message || $mode !== 'default')
    <div
        @if ($mode !== 'default')
            x-show="errorFor('{{ $name }}')"
            {{-- x-cloak тут нельзя: при отключённом JS он навсегда оставит
                 display:none и спрячет серверное сообщение. Инлайновый
                 style — только пока Alpine ещё не решил, показывать ли
                 блок; дальше показом управляет x-show. --}}
            @if (! $message) style="display: none" @endif
        @endif
        {{ $attributes }}
    >
        {{-- Обёртка нужна: у <x-ui.alert> уже свой x-data/x-show для кнопки
             закрытия (components/ui/alert.blade.php) — второй x-show на том
             же элементе через $attributes его бы затёр. --}}
        <x-ui.alert :tone="$tone" :title="$title">
            <span @if ($mode !== 'default') x-text="errorFor('{{ $name }}')" @endif>{{ $message }}</span>
        </x-ui.alert>
    </div>
@endif
