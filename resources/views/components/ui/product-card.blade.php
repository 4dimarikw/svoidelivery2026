{{-- Карточка товара для каталога (главная, route `home`). Воспроизводит .card
     из брендбука (data/design-system/project/design-system.html, §06) в
     токенах Tailwind.

     Анатомия сверху вниз: картинка-ссылка на страницу товара (+ избранное
     поверх неё, @auth) → плашка «Новинка» (если is_new) → производитель
     (mono) → заголовок brand ?: name (2 строки) → плашка стиля (только
     пиво) → строка «объём · тара» → mono-строка ABV/IBU/°P/EBC (только
     пиво) → чипы (рейтинг/нет в наличии) → .actions (цена + «Купить»/
     «Сообщить»).

     Кликабельна только картинка — как в брендбуке (design-system.html §06,
     <a class="thumb" href="#">), но не вся карточка целиком: <article>
     остаётся корневым элементом, favorite-toggle/степпер/«Купить» внутри
     него — свои независимые интерактивные элементы, вложенные в <a> они
     были бы невалидны. Ховер-штриховка картинки (.product-card-thumb в
     app.css) висит на самой ссылке вместе с её классом, см. ниже.

     .actions (цена + «Купить»/степпер) и кнопка избранного на картинке —
     обе только для @auth. Цена и кнопки действий не показываются гостю
     вовсе, не просто задизейблены — карточка гостя обрывается на чипах. Чтобы скрытие цены
     не было декоративным, фильтр «Цена, ₽» и сортировка по цене тоже
     выключены для гостя серверно (PriceRangeFilter::visible(),
     SortFilter::availableCases() — Domain\Catalog\Filters), а не только
     здесь в разметке. Состояние избранного сидируется с сервера через
     FavoriteManager::has() — первый рендер корректен без JS; клик —
     обычный POST-form, перехватываемый Alpine (uiFavoriteToggle,
     resources/js/favorites.js) с фолбэком на настоящую отправку формы при
     сбое fetch/JS. Товар «в избранном» отражается только цветом сердца
     (:class на .text-rust/.text-tan-500) — сердце (брендбук .card
     .thumb-wrap .fav) залито всегда, форма (пусто/залито) в передаче
     состояния не участвует, см. комментарий у кнопки ниже.

     «Купить» — <x-ui.cart-stepper> (Domain\Cart, CLAUDE.md; сам компонент —
     resources/views/components/ui/cart-stepper.blade.php), воспроизводит
     .qty из брендбука: при qty=0 видна обычная кнопка «Купить»/задизейбленное
     «Сообщить», при qty>0 — блок −/N/+ той же высоты; минус на количестве 1
     убирает товар и возвращает «Купить». Количество сидируется с сервера
     через CartManager::quantityOf() — та же мемоизация на запрос, что и у
     FavoriteManager::has() выше. Тот же компонент (без кнопки «Купить»,
     $showBuyButton=false) переиспользуется строками корзины на /cart
     (cart-line.blade.php) — там товар уже в корзине, «Купить»-состояния
     не существует.

     Заголовок — products.brand ("Марка" из 1С), а не name: name — сырая
     склейка 1С вида `Big Village "OLD SONG" (DDH NEDIPA) алк. 8,5% /
     Хейзи Дабл ИПА, ж/б 0,45л`, всё содержимое которой карточка уже
     показывает отдельными полями (производитель/тара/объём/стиль/ABV) —
     как заголовок она нечитаема и дублирует остальную карточку. name всё
     равно остаётся в разметке — в alt картинки, когда она есть, и всегда
     в title <article> (родной html-тултип), иначе у товаров без
     собственного изображения (заглушка вместо <img>, см. ниже) name нигде
     не появлялся бы вовсе.

     $product->beerDetails (HasOne на beer_product_details) есть только у
     товаров, для которых импорт нашёл хотя бы один пивной атрибут — у
     аксессуаров её нет вовсе, поэтому плашка стиля, 4-я строка
     (abv/ibu/plato/ebc) и чип рейтинга рендерятся только когда есть что
     показывать. Требует CatalogController::index() eager-load
     'beerDetails.beerStyle' и 'beerDetails.untappdBeer' — без этого N+1 на
     каждую карточку.

     $product->hasOwnImage() отличает настоящую медиа-картинку от
     заглушки Untappd (badge-beer-default-thumb) — при false рендерится
     <x-ui.product-placeholder> вместо <img>. Бьёт по уже eager-loaded
     'media', нового N+1 не создаёт.

     Компактный режим (< xl, сетка на 3/4/5 колонок — pages/home.blade.php,
     account/favorites/index.blade.php) переопределяет практически все
     размеры: `xl:` дальше по тексту — это возврат к исходным брендбучным
     значениям, а не «десктопный вариант поверх мобильного» — на lg-колонке
     карточка ýже, чем на md, поэтому обычная mobile-first лесенка здесь не
     работает, брендбук возвращается только на xl. По той же причине у
     превью нет фиксированного `min-h-[180px]`: на 5 колонках карточка
     никогда не шире ~160px, и фиксированный минимум растягивал бы превью
     в портрет.

     Кнопка «в избранное» лежит поверх картинки (absolute) — по брендбуку,
     вариант .card .thumb-wrap .fav: иконка поверх фото, без фона/рамки,
     крупнее (22px), заливка всегда сплошная (currentColor), без пары
     none/currentColor — состояние «в избранном» отличается только цветом
     (tan-500 → rust целиком через :class на favorited — НЕ дублировать
     text-tan-500 ещё и в статичном class: Alpine у строкового :class
     убирает только классы, добавленные им самим на предыдущем вычислении,
     авторский статичный class не трогает никогда, так что дубль остался
     бы в DOM навсегда рядом с text-rust, а в сгенерированном Tailwind CSS
     text-tan-500 идёт позже text-rust — обычный каскад безнадёжно
     проигрывал бы дубль, сердце просто никогда не перекрашивалось бы,
     несмотря на верный 200-ответ и верный favorited в Alpine), не формой.
     Тонкая кремовая обводка (stroke-cream-50,
     переопределяет hardcoded stroke="currentColor" в icon.blade.php —
     class-селектор всегда бьёт presentation-атрибут, icon.blade.php не
     трогаем) и drop-shadow-fav (tailwind.config.js — кремовый ореол +
     мягкая тёмная тень, брендбучные значения, две функции drop-shadow()
     цепочкой) держат сердце читаемым на любом фото без непрозрачной
     подложки. Сама форма кнопки/POST (uiFavoriteToggle) не меняется.

     overflow-hidden на .product-card-thumb — не только обрезка картинки
     под object-cover, а необходимое условие для самого aspect-square:
     <article> — flex flex-col, картинка внутри него — flex-item, а у
     flex-item'ов по умолчанию min-height:auto — автоматический минимум
     считается по контенту (родная высота/пропорции <img>) и может
     перебить высоту, которую диктует aspect-ratio, если фото само по
     себе высокое. overflow, отличный от visible, обнуляет этот
     автоматический минимум — без него превью тянется выше квадрата вслед
     за высокими фотографиями, несмотря на aspect-square. --}}
@props(['product'])

@php
    $beer = $product->beerDetails;
    $beerStyleName = $beer?->beerStyle?->name;

    // brand пуст только у товаров, у которых 1С не прислала "Марку" —
    // на сегодня таких нет, но колонка nullable, поэтому fallback.
    $title = $product->brand ?: $product->name;

    // Объём · тара (стиль — отдельной плашкой выше, не в этой строке).
    $spec = collect([
        $product->volume?->label,
        $product->container?->label ?: $product->container?->name,
    ])->filter();

    // Крепость/горечь/плотность/цвет — только пиво, каждое поле независимо
    // nullable даже когда $beer сама по себе существует.
    $numbers = collect([
        ($v = spec_number($beer?->abv)) ? $v.'%' : null,
        ($v = spec_number($beer?->ibu)) ? $v.' '.__('catalog.spec.ibu') : null,
        ($v = spec_number($beer?->plato)) ? $v.' '.__('catalog.spec.plato') : null,
        ($v = spec_number($beer?->ebc)) ? $v.' '.__('catalog.spec.ebc') : null,
    ])->filter();

    $rating = spec_number($beer?->untappdBeer?->rating_score);

    // Аналогично избранному (<x-ui.favorite-toggle>) — только для
    // авторизованных, мемоизировано в CartManager на один HTTP-запрос (см.
    // Domain\Cart\CartManager::items()).
    $cartQuantity = auth()->check() ? app(\Domain\Cart\CartManager::class)->quantityOf($product) : 0;
@endphp

<article
    title="{{ $product->name }}"
    class="flex h-full flex-col overflow-hidden rounded-sm border border-hairline bg-cream-50 transition hover:-translate-y-0.5 hover:shadow-md"
>
    <div class="relative aspect-square overflow-hidden border-b border-hairline bg-cream-100">
        <a href="{{ route('product.show', $product) }}" aria-label="{{ __('catalog.go_to_product') }}" class="product-card-thumb block h-full w-full">
            @if ($product->hasOwnImage())
                <img
                    src="{{ $product->thumb }}"
                    alt="{{ $product->name }}"
                    loading="lazy"
                    class="h-full w-full object-cover"
                >
            @else
                <x-ui.product-placeholder :product="$product" />
            @endif
        </a>

        <x-ui.favorite-toggle :product="$product" class="absolute right-2 top-2 z-10" />
    </div>

    @if ($product->is_new)
        <div class="border-b border-hairline bg-cream-200 px-2 py-1 font-mono text-[10px] uppercase leading-[14px] tracking-label text-tan-600 xl:px-5 xl:pb-1.5 xl:pt-[7px]">
            {{ __('catalog.new') }}
        </div>
    @endif

    <div class="flex flex-1 flex-col px-2 pb-2.5 pt-2 xl:px-5 xl:pb-5 xl:pt-4.5">
        @if ($product->manufacturer)
            <div class="font-mono text-badge uppercase text-ink-300">{{ $product->manufacturer->name }}</div>
        @endif

        <div class="mt-1 line-clamp-2 font-display text-caption font-bold uppercase leading-[1.15] tracking-brand-snug text-ink-900 xl:text-btn-lg">{{ $title }}</div>

        {{-- Брендбук (design-system.html §06 .style-tag) заливает эту плашку
             тем же teal-700/cream-100, что и .btn (app.css) — на реальной
             карточке кнопка «Купить» стоит ниже, и одинаковая заливка читается
             как два кликабельных элемента. Осознанное отклонение: светлая
             teal-100/teal-800 — та же пара, что badge.blade.php (default) и
             chip.blade.php (tone="teal") уже используют для некликабельных
             меток; форма/шрифт/регистр не меняются. --}}
        @if ($beerStyleName)
            <div class="mt-1.5 inline-flex self-start items-center rounded-sm bg-teal-100 px-1.5 py-0.5 font-display text-micro font-semibold uppercase tracking-[0.06em] text-teal-800 xl:px-2.5 xl:py-1">{{ $beerStyleName }}</div>
        @endif

        @if ($spec->isNotEmpty())
            <div class="mt-1.5 text-micro text-ink-500 xl:text-caption">{{ $spec->implode(' · ') }}</div>
        @endif

        @if ($numbers->isNotEmpty())
            <div class="mt-1.5 font-mono text-[11px] leading-4 tracking-[0.06em] text-teal-800">{{ $numbers->implode(' · ') }}</div>
        @endif

        {{-- Распорка: тело карточки — flex flex-1 flex-col, поэтому auto-margin
             здесь съедает всё свободное место и прижимает чипы + .actions к
             низу карточки одинаково у всех карточек ряда — независимо от
             того, сколько переменного контента (плашка стиля/спеки/ABV) выше.
             Именно здесь, не на .actions: у гостя .actions нет вовсе (@auth
             ниже), без этой распорки чипы гостевых карточек стояли бы на
             разной высоте. --}}
        <div class="mt-auto"></div>

        @if ($rating || ! $product->in_stock)
            <div class="-ml-1.5 mt-3 flex flex-wrap [&>*]:ml-1.5 [&>*]:mt-1.5">
                {{-- max-xl:!px-*/!py-* — chip.blade.php задаёт свой padding
                     через $attributes->class(), а в сгенерированном Tailwind
                     CSS px-2.5 (её дефолт) идёт позже px-1.5 в шкале
                     отступов независимо от порядка классов в HTML, так что
                     без ! мы проиграли бы каскад собственному паддингу
                     чипа. Ограничиваем важность до xl (max-xl:), чтобы на
                     xl вернулся обычный, ничем не переопределённый дефолт
                     компонента. Здесь только рейтинг и «нет в наличии» —
                     «Новинка» отдельная плашка под картинкой, см. выше. --}}
                @if ($rating)
                    <x-ui.chip tone="warn" class="max-xl:!px-1.5 max-xl:!py-1"><x-ui.icon name="star" :size="12" class="mr-1" />{{ $rating }}</x-ui.chip>
                @endif
                @if (! $product->in_stock)
                    <x-ui.chip tone="cream" class="max-xl:!px-1.5 max-xl:!py-1">{{ __('catalog.out_of_stock') }}</x-ui.chip>
                @endif
            </div>
        @endif

        {{-- Зазор чипы → линия фиксированный (mt-2.5, симметрично pt-2.5
             под линией; xl: возвращает брендбучные 3.5) — низ карточки
             прижимает распорка mt-auto выше, не эта строка.

             Избранное — на картинке (см. комментарий вверху файла), здесь
             только цена и степпер, друг под другом: в строку они не
             помещаются ни на одной колонке (3/4/5), включая xl. Высота
             степпера всё равно фиксирована — h-[41px], как .card .actions .qty{height:41px}
             в брендбуке (design-system.html §06/§10): без этого при qty=0
             блок тянет ~46px кнопка «Купить» (<x-ui.btn size="md">, padding
             12px + line-height 20px), а при qty>0 (кнопка скрыта x-show) —
             степпер своей высоты не имеет и схлопывается по цифре (~28px),
             из-за чего у карточки, на которой уже нажали «Купить», кнопки
             становятся заметно меньше, чем у соседних. --}}
        @auth
            <div class="mt-2.5 border-t border-hairline pt-2.5 xl:mt-3.5 xl:pt-3.5">
                <span class="block whitespace-nowrap font-display text-btn-lg font-bold tracking-brand-body text-ink-900 xl:text-heading-s">{{ $product->price }}</span>

                <x-ui.cart-stepper :product="$product" :quantity="$cartQuantity" class="mt-2 h-[41px]" />
            </div>
        @endauth
    </div>
</article>
