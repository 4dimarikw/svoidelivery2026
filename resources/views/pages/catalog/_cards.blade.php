{{-- Партиал карточек товара. Рендерится и как часть pages/home.blade.php,
     и как ajax-фрагмент для бесконечного скролла (см. CatalogController::index()
     и resources/js/catalog.js) — разметка карточки существует в одном месте. --}}
@foreach ($products as $product)
    <x-ui.product-card :product="$product" :remove-on-unfavorite="$removeOnUnfavorite ?? false" />
@endforeach
