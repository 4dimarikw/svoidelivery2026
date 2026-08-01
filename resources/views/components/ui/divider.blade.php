{{-- Slot is the label word (design system's "или" pattern). Class .divider
     is defined in @layer components — see resources/css/app.css. --}}
<div {{ $attributes->class('divider') }}>{{ $slot }}</div>
