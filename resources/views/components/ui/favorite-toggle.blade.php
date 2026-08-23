{{-- Кнопка-сердечко «в избранное» — общая для карточки каталога
     (product-card.blade.php) и страницы товара (pages/product.blade.php).
     Позиционирование — снаружи, через $attributes на <form> (карточка
     кладёт её абсолютно поверх картинки, страница товара — в обычном
     потоке рядом с ценой), сама кнопка/иконка/JS не меняются.

     Только для авторизованных — у избранного нет гостевого режима, см.
     CLAUDE.md/Domain\Favorite. FavoriteManager::has() и productIds() внутри
     него мемоизированы на запрос. --}}
@props(['product'])

@auth
    @php
        $isFavorited = app(\Domain\Favorite\FavoriteManager::class)->has($product);
    @endphp

    <form
        method="POST"
        action="{{ route('account.favorites.toggle', $product) }}"
        x-data="uiFavoriteToggle(@js($isFavorited), {{ $product->id }})"
        x-on:submit.prevent="toggle($el)"
        {{ $attributes }}
    >
        @csrf
        <button
            type="submit"
            :aria-pressed="favorited"
            {{-- hover:text-rust живёт только в динамической ветке — статичный
                 класс с той же специфичностью (0,2,0 против 0,1,0 у
                 text-tan-500) побеждал бы :class всегда, включая залипающий
                 :hover после тапа на мобильном (снятие с избранного —
                 единственное направление на /account/favorites, и как раз
                 то, что это маскировало). --}}
            :class="favorited ? 'text-rust' : 'text-tan-500 hover:text-rust'"
            class="grid h-[34px] w-[34px] place-items-center drop-shadow-fav transition"
            :aria-label="favorited ? @js(__('catalog.remove_from_favorites')) : @js(__('catalog.add_to_favorites'))"
        >
            <x-ui.icon name="heart" :size="22" :stroke="1.25" fill="currentColor" class="stroke-cream-50" />
        </button>
    </form>
@endauth
