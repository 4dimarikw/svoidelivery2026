{{-- Карточка товара для каталога (главная, route `home`). Воспроизводит .card
     из брендбука (data/design-system/project/design-system.html, §06) в
     токенах Tailwind.

     Анатомия сверху вниз: картинка → производитель (mono) → заголовок
     brand ?: name (2 строки) → плашка стиля (только пиво) → строка
     «объём · тара» → mono-строка ABV/IBU/°P/EBC (только пиво) → чипы →
     .actions (цена + «Купить»/«Сообщить» + избранное).

     Сознательное расхождение с брендбуком — в проекте нет страницы товара,
     поэтому <article>, не <a class="thumb" href="#">: незаглушаемая мёртвая
     ссылка на несуществующую страницу, см. CLAUDE.md. Ховер-штриховка
     картинки (.product-card-thumb в app.css) оставлена чисто декоративно.

     Вся .actions (цена + «Купить»/степпер + избранное) — только для @auth.
     Цена и кнопки действий не показываются гостю вовсе, не просто
     задизейблены — карточка гостя обрывается на чипах. Чтобы скрытие цены
     не было декоративным, фильтр «Цена, ₽» и сортировка по цене тоже
     выключены для гостя серверно (PriceRangeFilter::visible(),
     SortFilter::availableCases() — Domain\Catalog\Filters), а не только
     здесь в разметке. Состояние избранного сидируется с сервера через
     FavoriteManager::has() — первый рендер корректен без JS; клик —
     обычный POST-form, перехватываемый Alpine (uiFavoriteToggle,
     resources/js/favorites.js) с фолбэком на настоящую отправку формы при
     сбое fetch/JS. Товар «в избранном» отражается и цветом контура кнопки
     (:class на .border-rust/.text-rust), и заливкой самого сердца —
     <x-ui.icon fill="…"> сидируется с сервера ($isFavorited) и
     перекрывается Alpine-биндингом (::fill, favorited) после клика.

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
     'media', нового N+1 не создаёт. --}}
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

    // Только для авторизованных — у избранного нет гостевого режима (см.
    // CLAUDE.md/Domain\Favorite). productIds() внутри FavoriteManager
    // мемоизирован на запрос, поэтому 24 карточки на странице бьют в БД
    // один раз, а не по разу на карточку.
    $isFavorited = auth()->check() && app(\Domain\Favorite\FavoriteManager::class)->has($product);

    // Аналогично избранному — только для авторизованных, мемоизировано в
    // CartManager на один HTTP-запрос (см. Domain\Cart\CartManager::items()).
    $cartQuantity = auth()->check() ? app(\Domain\Cart\CartManager::class)->quantityOf($product) : 0;
@endphp

<article
    title="{{ $product->name }}"
    class="flex h-full flex-col overflow-hidden rounded-sm border border-hairline bg-cream-50 transition hover:-translate-y-0.5 hover:shadow-md"
>
    <div class="product-card-thumb aspect-[4/3] min-h-[180px] border-b border-hairline bg-cream-100">
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
    </div>

    <div class="flex flex-1 flex-col px-5 pb-5 pt-4.5">
        @if ($product->manufacturer)
            <div class="font-mono text-badge uppercase text-ink-300">{{ $product->manufacturer->name }}</div>
        @endif

        <div class="mt-1 line-clamp-2 font-display text-btn-lg font-bold uppercase leading-[1.15] tracking-brand-snug text-ink-900">{{ $title }}</div>

        @if ($beerStyleName)
            <div class="mt-1.5 inline-flex self-start items-center rounded-sm bg-teal-700 px-2.5 py-1 font-display text-micro font-semibold uppercase tracking-[0.06em] text-cream-100">{{ $beerStyleName }}</div>
        @endif

        @if ($spec->isNotEmpty())
            <div class="mt-1.5 text-caption text-ink-500">{{ $spec->implode(' · ') }}</div>
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

        @if ($product->is_new || $rating || ! $product->in_stock)
            <div class="-ml-1.5 mt-3 flex flex-wrap [&>*]:ml-1.5 [&>*]:mt-1.5">
                @if ($product->is_new)
                    <x-ui.chip tone="rust">{{ __('catalog.new') }}</x-ui.chip>
                @endif
                @if ($rating)
                    <x-ui.chip tone="warn"><x-ui.icon name="star" :size="12" class="mr-1" />{{ $rating }}</x-ui.chip>
                @endif
                @if (! $product->in_stock)
                    <x-ui.chip tone="cream">{{ __('catalog.out_of_stock') }}</x-ui.chip>
                @endif
            </div>
        @endif

        {{-- Зазор чипы → линия теперь фиксированный (mt-3.5, симметрично
             pt-3.5 под линией) — низ карточки прижимает распорка mt-auto
             выше, не эта строка. Высота самой строки действий тоже
             фиксирована — h-[41px] на внутреннем flex-контейнере, как
             .card .actions .qty{height:41px} в брендбуке (design-system.html
             §06/§10): без этого при qty=0 строку тянет ~46px кнопка «Купить»
             (<x-ui.btn size="md">, padding 12px + line-height 20px), а при
             qty>0 (кнопка скрыта x-show) — степпер/избранное своей высоты не
             имеют и схлопываются по цене (~28px), из-за чего у карточки, на
             которой уже нажали «Купить», кнопки становятся заметно меньше,
             чем у соседних. h-[41px] — на вложенном div, не на внешнем
             (border-t + pt-3.5 в box-border иначе съели бы часть высоты из
             тех же 41px и кнопки всё равно схлопнулись бы).

             Без items-center на внутреннем div: flex по умолчанию stretch —
             избранное и степпер растягиваются на все 41px сами.

             mr-3 на цене, не mr-auto: у соседней кнопки flex-1 — по
             спецификации flexbox flex-grow забирает свободное место раньше,
             чем auto-margin успевает что-то поглотить на этапе выравнивания,
             так что mr-auto тут резолвился бы в ~0px (цена оказывалась
             впритык к кнопке). --}}
        @auth
            <div class="mt-3.5 border-t border-hairline pt-3.5">
                <div class="flex h-[41px]">
                    <span class="mr-3 self-center whitespace-nowrap font-display text-heading-s font-bold tracking-brand-body text-ink-900">{{ $product->price }}</span>

                    <x-ui.cart-stepper :product="$product" :quantity="$cartQuantity" class="flex-1" />

                    <form
                        method="POST"
                        action="{{ route('account.favorites.toggle', $product) }}"
                        x-data="uiFavoriteToggle(@js($isFavorited))"
                        x-on:submit.prevent="toggle($el)"
                        class="ml-2 shrink-0"
                    >
                        @csrf
                        <button
                            type="submit"
                            :aria-pressed="favorited"
                            :class="favorited ? 'border-rust text-rust' : 'border-hairline text-ink-500'"
                            class="grid h-full w-[41px] place-items-center rounded-sm border bg-cream-50 transition hover:border-rust hover:text-rust"
                            :aria-label="favorited ? @js(__('catalog.remove_from_favorites')) : @js(__('catalog.add_to_favorites'))"
                        >
                            <x-ui.icon
                                name="heart"
                                :size="18"
                                :fill="$isFavorited ? 'currentColor' : 'none'"
                                ::fill="favorited ? 'currentColor' : 'none'"
                            />
                        </button>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</article>
