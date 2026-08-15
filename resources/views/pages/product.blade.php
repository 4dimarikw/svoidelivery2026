{{-- Страница товара (route `product.show`, ProductController::show()).
     $product — со всеми связями, загруженными контроллером (category,
     manufacturer, volume, container, media, beerDetails.beerStyle,
     beerDetails.untappdBeer). $similar — до 5 товаров того же стиля пива
     (или категории, если стиля нет), сам товар исключён.

     Цена/степпер/избранное — только @auth, как в product-card.blade.php:
     у гостя для них нет серверных данных (CartManager/FavoriteManager
     работают только с авторизованным пользователем, см. CLAUDE.md/Domain\Cart
     и Domain\Favorite), а не потому что это "premium"-контент.

     От Untappd на странице сознательно показываются только чип рейтинга (в
     блоке чипов) и ссылка «Смотреть на Untappd» — собственное описание
     untappdBeer->description не выводится: оно на английском и дублирует
     по смыслу products.description (тоже не выводится второй раз, brewery
     тем более — тот же производитель уже показан строкой выше и в таблице
     характеристик). --}}
@php
    use Domain\Cart\CartManager;$beer = $product->beerDetails;
    $beerStyleName = $beer?->beerStyle?->name;
    $title = $product->brand ?: $product->name;

    $spec = collect([
        $product->volume?->label,
        $product->container?->label ?: $product->container?->name,
    ])->filter();

    $rating = spec_number($beer?->untappdBeer?->rating_score);
//    $ratingCount = $beer?->untappdBeer?->rating_count;

    $cartQuantity = auth()->check() ? app(CartManager::class)->quantityOf($product) : 0;

    // Строки характеристик — только непустые. Учётные поля (артикул, штук
    // в упаковке, срок годности) сознательно не выводятся — служебные,
    // покупателю не нужны. Числа — тем же spec_number(), что и карточка.
    $specRows = collect([
        ['label' => __('catalog.product.spec_labels.abv'), 'value' => ($v = spec_number($beer?->abv)) ? $v.'%' : null],
        ['label' => __('catalog.product.spec_labels.ibu'), 'value' => ($v = spec_number($beer?->ibu)) ? $v.' '.__('catalog.spec.ibu') : null],
        ['label' => __('catalog.product.spec_labels.plato'), 'value' => ($v = spec_number($beer?->plato)) ? $v.' '.__('catalog.spec.plato') : null],
        ['label' => __('catalog.product.spec_labels.ebc'), 'value' => ($v = spec_number($beer?->ebc)) ? $v.' '.__('catalog.spec.ebc') : null],
        ['label' => __('catalog.product.spec_labels.style'), 'value' => $beerStyleName],
        ['label' => __('catalog.product.spec_labels.volume'), 'value' => $product->volume?->label],
        ['label' => __('catalog.product.spec_labels.container'), 'value' => $product->container?->label ?: $product->container?->name],
        ['label' => __('catalog.product.spec_labels.category'), 'value' => $product->category?->name],
        ['label' => __('catalog.product.spec_labels.manufacturer'), 'value' => $product->manufacturer?->name],
    ])->filter(fn ($row) => filled($row['value']));
@endphp

<x-layouts.site :title="$title">
    <div class="mx-auto max-w-page px-6 py-10">
        <x-ui.breadcrumbs :items="[
            ['label' => __('catalog.title'), 'url' => route('home')],
            ...($product->category ? [['label' => $product->category->name, 'url' => route('home', ['categories' => [$product->category_id]])]] : []),
            ['label' => $title],
        ]" class="mb-6"/>

        <div class="grid gap-8 md:grid-cols-2 md:gap-10">
            <div class="relative aspect-square overflow-hidden border border-hairline bg-cream-100">
                @if ($product->hasOwnImage())
                    {{-- label — полноразмерная конверсия (Product::registerMediaConversions()),
                         не thumb (234px, для карточек каталога). --}}
                    <img src="{{ $product->label }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                @else
                    <x-ui.product-placeholder :product="$product"/>
                @endif
            </div>

            <div>
                @if ($product->manufacturer)
                    <div class="font-mono text-badge uppercase text-ink-300">{{ $product->manufacturer->name }}</div>
                @endif

                <h1 class="mt-1 font-display text-heading-s font-bold uppercase leading-[1.15] tracking-brand-snug text-ink-900 sm:text-heading-m">{{ $title }}</h1>

                @if ($beerStyleName)
                    <div
                        class="mt-2 inline-flex self-start items-center rounded-sm bg-teal-700 px-2.5 py-1 font-display text-micro font-semibold uppercase tracking-[0.06em] text-cream-100">{{ $beerStyleName }}</div>
                @endif

                @if ($spec->isNotEmpty())
                    <div class="mt-2 text-caption text-ink-500">{{ $spec->implode(' · ') }}</div>
                @endif

                @if ($product->is_new || $rating || ! $product->in_stock)
                    <div class="-ml-1.5 mt-3 flex flex-wrap [&>*]:ml-1.5 [&>*]:mt-1.5">
                        @if ($product->is_new)
                            <x-ui.chip tone="rust">{{ __('catalog.new') }}</x-ui.chip>
                        @endif
                        @if ($rating)
                            <x-ui.chip tone="warn">
                                <x-ui.icon name="star" :size="12" class="mr-1"/>{{ $rating }}
                                {{--                                @if ($ratingCount)--}}
                                {{--                                    <span class="ml-1 text-ink-500">({{ $ratingCount }})</span>--}}
                                {{--                                @endif--}}
                            </x-ui.chip>
                        @endif
                        @if (! $product->in_stock)
                            <x-ui.chip tone="cream">{{ __('catalog.out_of_stock') }}</x-ui.chip>
                        @endif
                    </div>
                @endif

                @if ($product->description)
                    <div class="mt-5 border-t border-hairline pt-5">
                        <h2 class="font-mono text-label uppercase tracking-label text-ink-500">{{ __('catalog.product.description') }}</h2>
                        {{-- {!! !!}, не {{ }}: $product->description уже прошло
                             Support\Casts\PurifiedHtml (профиль product_description)
                             и на записи, и на чтении — безопасно рендерить как HTML.
                             div, не p: описание само может содержать <p> (разрешён
                             профилем), вложенный <p> в <p> невалиден и ломает разметку. --}}
                        <div
                            class="mt-2 whitespace-pre-line text-body-m text-ink-700">{!! $product->description !!}</div>
                    </div>
                @endif

                @if ($beer?->untappdBeer?->url)
                    <x-ui.link :href="$beer->untappdBeer->url" target="_blank" rel="noopener" class="mt-3 inline-block">
                        {{ __('catalog.product.untappd_link') }}
                    </x-ui.link>
                @endif

                @auth
                    <div class="mt-5 border-t border-hairline pt-5">
                        <span
                            class="block whitespace-nowrap font-display text-heading-s font-bold tracking-brand-body text-ink-900">{{ $product->price }}</span>

                        <div class="mt-3 flex items-stretch gap-3">
                            <x-ui.cart-stepper :product="$product" :quantity="$cartQuantity" class="h-[41px] flex-1"/>
                            <x-ui.favorite-toggle :product="$product" class="shrink-0"/>
                        </div>
                    </div>
                @endauth
            </div>
        </div>

        @if ($specRows->isNotEmpty())
            <div class="mt-10 max-w-measure">
                <h2 class="font-display text-heading-s uppercase text-ink-900">{{ __('catalog.product.specs') }}</h2>

                <dl class="mt-4 divide-y divide-hairline border-t border-hairline">
                    @foreach ($specRows as $row)
                        <div class="flex items-baseline justify-between py-2.5 text-body-m">
                            <dt class="text-ink-500">{{ $row['label'] }}</dt>
                            <dd class="text-ink-900">{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        @if ($similar->isNotEmpty())
            <div class="mt-14">
                <h2 class="font-display text-heading-s uppercase text-ink-900">{{ __('catalog.product.similar') }}</h2>

                <div class="mt-4 grid grid-cols-2 gap-1 sm:gap-4 md:grid-cols-4 lg:grid-cols-5 xl:gap-1">
                    @foreach ($similar as $item)
                        <x-ui.product-card :product="$item"/>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-layouts.site>
