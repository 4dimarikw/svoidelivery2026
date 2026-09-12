{{-- Слот содержит SVG QR-кода от доверенного генератора BaconQrCode; ComponentSlot реализует Htmlable. Белая зона
   вокруг кода облегчает сканирование. Альтернативный текст и выделяемый код ручного ввода обеспечивают
   доступность. Интервалы заданы margin для совместимости с Safari без flex-gap. --}}
@props([
    'alt',
    'secret' => null,
])

<div {{ $attributes->class('flex flex-col items-center [&>*+*]:mt-4') }}>
    <div role="img" aria-label="{{ $alt }}" class="rounded-sm bg-white p-4">
        {{ $slot }}
    </div>

    @if ($secret)
        <p class="select-all font-mono text-micro text-ink-700">{{ $secret }}</p>
    @endif
</div>
