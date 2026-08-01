{{-- The QR code SVG is the slot content — e.g.
     {!! auth()->user()->twoFactorQrCodeSvg() !!} in Fortify's 2FA-enable
     view. It's framework-controlled output from BaconQrCode, safe to echo
     unescaped; `{{ $slot }}` doesn't HTML-escape it here because
     ComponentSlot implements Htmlable — don't "fix" that.

     A pure-white inner box, not cream-50: QR scanners rely on a clean quiet
     zone and off-white can hurt scan reliability. The QR alone is unusable
     without a camera or for screen readers, so `alt` + a selectable manual
     entry code are both required, not decorative. --}}
@props([
    'alt',
    'secret' => null,
])

<div {{ $attributes->class('flex flex-col items-center gap-4') }}>
    <div role="img" aria-label="{{ $alt }}" class="rounded-sm bg-white p-4">
        {{ $slot }}
    </div>

    @if ($secret)
        <p class="select-all font-mono text-micro text-ink-700">{{ $secret }}</p>
    @endif
</div>
