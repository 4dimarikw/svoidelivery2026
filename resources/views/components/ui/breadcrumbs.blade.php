{{-- Хлебные крошки — в брендбуке своей секции нет (первый экран, где они
     нужны — страница товара), верстаются по токенам §03/§04. Разделитель —
     отдельный <span aria-hidden>, отступы margin-based (mx-*), не flex
     gap — легаси-браузеры, см. CLAUDE.md/шапку сайта. --}}
@props(['items'])

<nav aria-label="{{ __('catalog.breadcrumbs') }}" {{ $attributes }}>
    <ol class="flex flex-wrap items-center font-mono text-badge uppercase tracking-label text-ink-300">
        @foreach ($items as $item)
            <li class="flex items-center">
                @if (! $loop->first)
                    <span aria-hidden="true" class="mx-1.5">/</span>
                @endif

                @if (! empty($item['url']) && ! $loop->last)
                    <a href="{{ $item['url'] }}" class="hover:text-teal-700">{{ $item['label'] }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" @endif class="text-ink-500">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
