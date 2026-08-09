{{-- Главное меню сайта, CMS-driven ($items — дерево из LoadSiteMenu,
     сидируется в шапку через View Composer, см. AppServiceProvider::boot()).
     Только desktop (md:) — hidden md:flex, не PHP-условие — тот же приём,
     что у auth-блока в header.blade.php. Ниже `md:` эти пункты нигде не
     дублируются: <x-ui.mobile-nav> (нижняя мобильная панель) к этому
     CMS-меню намеренно не привязана, её ссылки хардкожены отдельно.

     Поддержан один уровень вложенности: пункт с детьми — выпадашка по
     образцу <x-ui.user-menu> (x-data, click-outside, Escape, x-cloak).
     Триггер — настоящая <a> на URL самого пункта с x-on:click.prevent:
     без JS клик всё равно ведёт на страницу пункта, а не в никуда.

     space-x-*, не flex gap — Safari < 14.1 не поддерживает flex-gap (см.
     CLAUDE.md, legacy-browser target); внутри выпадашки grid gap-* уже
     можно, запрет только на flex.

     Стиль самого пункта (текст/подчёркивание) — портирован из брандбука
     `data/design-system/project/design-system.html`, секция «Шапка
     сайта», вариант H4 · Упрощённая классическая (`.sh-a nav.main a`):
     13px/500/tracking .01em, padding 6px 0, нижняя рамка 2px — прозрачная
     в покое, teal-500 на hover, teal-700 в активном состоянии. Только
     типографика/подчёркивание пункта — контейнер (space-x-4, не gap-*),
     dropdown-панель вложенных пунктов и chevron это НЕ затрагивает: у
     H4 нет вложенности, это наша собственная надстройка. --}}
@props(['items' => []])

@if (! empty($items))
    <nav id="main-nav" aria-label="{{ __('layout.nav.primary') }}" class="hidden items-center space-x-4 md:flex">
        @foreach ($items as $item)
            @php
                $isActive = \Illuminate\Support\Str::before($item['url'], '#') === url()->current();
            @endphp

            @if (empty($item['children']))
                <a
                    href="{{ $item['url'] }}"
                    target="{{ $item['target'] }}"
                    @if ($item['rel']) rel="{{ $item['rel'] }}" @endif
                    @if ($isActive) aria-current="page" @endif
                    class="whitespace-nowrap border-b-2 py-1.5 text-caption font-medium tracking-[0.01em] transition {{ $isActive ? 'border-teal-700 text-teal-700' : 'border-transparent text-ink-700 hover:border-teal-500 hover:text-teal-700' }}"
                >{{ $item['label'] }}</a>
            @else
                <div
                    class="relative"
                    x-data="{ open: false }"
                    x-on:keydown.escape.window="open = false"
                    x-on:click.outside="open = false"
                >
                    <a
                        href="{{ $item['url'] }}"
                        target="{{ $item['target'] }}"
                        @if ($item['rel']) rel="{{ $item['rel'] }}" @endif
                        x-on:click.prevent="open = ! open"
                        aria-haspopup="true"
                        :aria-expanded="open"
                        @if ($isActive) aria-current="page" @endif
                        class="inline-flex items-center whitespace-nowrap border-b-2 py-1.5 text-caption font-medium tracking-[0.01em] transition {{ $isActive ? 'border-teal-700 text-teal-700' : 'border-transparent text-ink-700 hover:border-teal-500 hover:text-teal-700' }}"
                    >
                        {{ $item['label'] }}
                        <x-ui.icon name="chevron-down" :size="16" class="ml-1 transition" ::class="{ 'rotate-180': open }" />
                    </a>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition.opacity
                        class="absolute left-0 z-30 mt-2 w-56 grid gap-0.5 rounded-sm border border-hairline bg-cream-50 p-2 shadow-md"
                    >
                        @foreach ($item['children'] as $child)
                            <a
                                href="{{ $child['url'] }}"
                                target="{{ $child['target'] }}"
                                @if ($child['rel']) rel="{{ $child['rel'] }}" @endif
                                class="flex items-center rounded-sm px-3.5 py-2.5 text-body-m text-ink-700 hover:bg-cream-200 hover:text-ink-900"
                            >{{ $child['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>
@endif
