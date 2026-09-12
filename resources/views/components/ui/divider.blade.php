{{-- Слот содержит подпись разделителя. Класс divider определён в слое components файла resources/css/app.css. --}}
<div {{ $attributes->class('divider') }}>{{ $slot }}</div>
