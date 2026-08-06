<?php

namespace Tests\Feature;

use Domain\Auth\Models\User;
use Domain\Catalog\Enums\ProductSort;
use Domain\Catalog\Enums\ProductStatus;
use Domain\Catalog\Models\BeerProductDetail;
use Domain\Catalog\Models\BeerStyle;
use Domain\Catalog\Models\Category;
use Domain\Catalog\Models\Container;
use Domain\Catalog\Models\Manufacturer;
use Domain\Catalog\Models\Product;
use Domain\Catalog\Models\Volume;
use Domain\Favorite\Models\Favorite;
use Domain\Untappd\Models\UntappdBeer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_with_header_and_footer(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(__('account.login.submit'));
    }

    public function test_mobile_filters_toggle_renders_with_alpine_bindings_intact(): void
    {
        // Регрессия: <x-ui.btn ::aria-expanded="filtersOpen"> — двойное двоеточие
        // обязательно, иначе Blade трактует "filtersOpen" как PHP-выражение
        // (константу) вместо буквенной Alpine-строки и падает с ошибкой рендера.
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('id="catalog-filters-panel"', false);
        $response->assertSee(':aria-expanded="filtersOpen"', false);
        $response->assertSee('aria-controls="catalog-filters-panel"', false);
        $response->assertSee('x-on:click="filtersOpen = !filtersOpen"', false);
        $response->assertSee('x-on:click="filtersOpen = false"', false);
    }

    public function test_select_filter_renders_with_alpine_bindings_intact(): void
    {
        // Регрессия того же класса бага: <x-ui.select-filter> использует
        // :aria-expanded="open" на НАТИВНОМ <button> (не <x-...> теге) — тут
        // одиночное двоеточие корректно, Blade его не трогает. Проверяем, что
        // в HTML буквально остаётся Alpine-выражение, а не PHP-константа/ошибка.
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('select-wrap', false);
        $response->assertSee(':aria-expanded="open"', false);
        $response->assertSee('x-on:click.outside="open = false"', false);
        $response->assertSee($category->name);
    }

    public function test_home_page_lists_published_in_stock_products(): void
    {
        $shown = Product::factory()->create(['name' => 'Тёмный лагер', 'status' => ProductStatus::PUBLISHED, 'in_stock' => true]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee($shown->name);
    }

    public function test_draft_and_archived_products_are_not_listed(): void
    {
        $draft = Product::factory()->create(['name' => 'Черновик Пиво', 'status' => ProductStatus::DRAFT]);
        $archived = Product::factory()->create(['name' => 'Архивное Пиво', 'status' => ProductStatus::ARCHIVED]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee($draft->name);
        $response->assertDontSee($archived->name);
    }

    public function test_category_filter_narrows_results(): void
    {
        $wantedCategory = Category::factory()->create();
        $otherCategory = Category::factory()->create();

        $wanted = Product::factory()->create(['name' => 'Хочу Это', 'category_id' => $wantedCategory->id]);
        $other = Product::factory()->create(['name' => 'Не Это', 'category_id' => $otherCategory->id]);

        $response = $this->get(route('home', ['categories' => [$wantedCategory->id]]));

        $response->assertOk();
        $response->assertSee($wanted->name);
        $response->assertDontSee($other->name);
    }

    public function test_price_range_filter_narrows_results(): void
    {
        // price_min/price_max — гостю недоступны (PriceRangeFilter::visible()),
        // фильтр по цене задействован только авторизованным.
        $cheap = Product::factory()->create(['name' => 'Дешёвое', 'price' => 100]);
        $expensive = Product::factory()->create(['name' => 'Дорогое', 'price' => 4000]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('home', ['price_min' => 500, 'price_max' => 5000]));

        $response->assertOk();
        $response->assertSee($expensive->name);
        $response->assertDontSee($cheap->name);
    }

    public function test_guest_price_range_query_params_are_ignored(): void
    {
        // Гость не может подобрать цену подставив price_min/price_max в URL —
        // PriceRangeFilter невидим, значит не применяется в пайплайне.
        $cheap = Product::factory()->create(['name' => 'Дешёвое', 'price' => 100]);
        $expensive = Product::factory()->create(['name' => 'Дорогое', 'price' => 4000]);

        $response = $this->get(route('home', ['price_min' => 500, 'price_max' => 5000]));

        $response->assertOk();
        $response->assertSee($cheap->name);
        $response->assertSee($expensive->name);
    }

    public function test_guest_does_not_see_the_price_filter_widget(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee(__('catalog.filters.price'));
    }

    public function test_authenticated_user_sees_the_price_filter_widget(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('home'));

        $response->assertOk();
        $response->assertSee(__('catalog.filters.price'));
    }

    public function test_in_stock_filter_hides_out_of_stock_products(): void
    {
        $inStock = Product::factory()->create(['name' => 'В Наличии', 'in_stock' => true]);
        $outOfStock = Product::factory()->create(['name' => 'Нет В Наличии', 'in_stock' => false]);

        $response = $this->get(route('home', ['in_stock' => 1]));

        $response->assertOk();
        $response->assertSee($inStock->name);
        $response->assertDontSee($outOfStock->name);
    }

    public function test_search_filter_matches_product_name(): void
    {
        $match = Product::factory()->create(['name' => 'Хмельной эль']);
        $nonMatch = Product::factory()->create(['name' => 'Тёмный портер']);

        $response = $this->get(route('home', ['q' => 'Хмельн']));

        $response->assertOk();
        $response->assertSee($match->name);
        $response->assertDontSee($nonMatch->name);
    }

    public function test_manufacturer_volume_and_container_filters_narrow_results(): void
    {
        $manufacturer = Manufacturer::factory()->create();
        $volume = Volume::factory()->create();
        $container = Container::factory()->create();

        $wanted = Product::factory()->create([
            'name' => 'Точное Совпадение',
            'manufacturer_id' => $manufacturer->id,
            'volume_id' => $volume->id,
            'container_id' => $container->id,
        ]);
        $other = Product::factory()->create(['name' => 'Другое']);

        $response = $this->get(route('home', [
            'manufacturers' => [$manufacturer->id],
            'volumes' => [$volume->id],
            'containers' => [$container->id],
        ]));

        $response->assertOk();
        $response->assertSee($wanted->name);
        $response->assertDontSee($other->name);
    }

    public function test_container_filter_options_prefer_label_over_name(): void
    {
        Container::factory()->create(['name' => 'Алюминиевая банка', 'label' => 'Жестяная банка']);
        Container::factory()->create(['name' => 'Стеклянная бутылка', 'label' => null]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Жестяная банка');
        $response->assertDontSee('Алюминиевая банка');
        $response->assertSee('Стеклянная бутылка');
    }

    public function test_product_card_renders_full_spec_for_beer(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Балтика']);
        $beerStyle = BeerStyle::factory()->create(['name' => 'IPA']);
        $untappdBeer = UntappdBeer::factory()->create(['rating_score' => 3.85]);

        $product = Product::factory()->create([
            'name' => 'Пивной Хит',
            'manufacturer_id' => $manufacturer->id,
            'brand' => 'Жигулёвское',
            'volume_id' => Volume::factory()->create(['label' => '0,5 л'])->id,
            'container_id' => Container::factory()->create(['name' => 'Стеклянная бутылка', 'label' => null])->id,
        ]);

        BeerProductDetail::factory()->create([
            'product_id' => $product->id,
            'beer_style_id' => $beerStyle->id,
            'untappd_beer_id' => $untappdBeer->id,
            'abv' => 5.8,
            'ibu' => 40,
            'plato' => 12.5,
            'ebc' => 20,
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('home'));

        $response->assertOk();
        $response->assertSee('Балтика');
        $response->assertSee('Жигулёвское');
        $response->assertSee('IPA');
        $response->assertSee('5,8%', false);
        $response->assertSee('40 IBU');
        $response->assertSee('12,5 °P', false);
        $response->assertSee('20 EBC');
        $response->assertSee('3,85');
        $response->assertSee('Купить');
    }

    public function test_product_card_omits_beer_row_for_non_beer_product(): void
    {
        Product::factory()->create(['name' => 'Аксессуар Без Пива']);

        $response = $this->get(route('home'));

        // 'IBU' появляется в заголовке фильтра «Горечь, IBU» независимо от
        // товаров, поэтому проверяем отсутствие конкретно отформатированных
        // чисел карточки, а не голого слова.
        $response->assertOk();
        $response->assertDontSee('EBC');
        $response->assertDontSee('°P', false);
    }

    public function test_product_card_omits_missing_beer_values(): void
    {
        $product = Product::factory()->create(['name' => 'Частично Заполненное Пиво']);

        BeerProductDetail::factory()->create([
            'product_id' => $product->id,
            'abv' => 5.8,
            'ibu' => null,
            'plato' => null,
            'ebc' => null,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('5,8%', false);
        $response->assertDontSee('°P', false);
        $response->assertDontSee('EBC');
    }

    public function test_product_card_shows_report_button_when_out_of_stock(): void
    {
        Product::factory()->create(['brand' => 'Закончилось', 'in_stock' => false]);

        $response = $this->actingAs(User::factory()->create())->get(route('home'));

        $response->assertOk();
        $response->assertSee('Сообщить');
        $response->assertDontSee('>Купить<', false);
    }

    public function test_guest_does_not_see_the_favorite_button(): void
    {
        // Избранное — только для авторизованных (Domain\Favorite, CLAUDE.md):
        // гость не видит кнопку вовсе, а не "недоступную"/декоративную версию.
        Product::factory()->create();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('account/favorites', false);
        $response->assertDontSee(__('catalog.add_to_favorites'));
    }

    public function test_authenticated_user_sees_the_favorite_button(): void
    {
        $user = User::factory()->create();
        Product::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('account/favorites', false);
    }

    public function test_favorited_product_renders_a_filled_heart(): void
    {
        // Сердце закрашивается (fill="currentColor") для товара, уже
        // добавленного в избранное — и серверным сидом (:fill), и
        // Alpine-биндингом (::fill) на случай клика без перезагрузки.
        $user = User::factory()->create();
        $product = Product::factory()->create();
        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertSee('fill="currentColor"', false);
        $response->assertSee(':fill="favorited ? \'currentColor\' : \'none\'"', false);
    }

    public function test_non_favorited_product_renders_an_empty_heart(): void
    {
        $user = User::factory()->create();
        Product::factory()->create();

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('fill="currentColor"', false);
    }

    public function test_guest_does_not_see_price_or_action_buttons(): void
    {
        // Цена и кнопки действий — только для @auth (см. product-card.blade.php).
        Product::factory()->create(['price' => 1234]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('₽ 1 234');
        $response->assertDontSee(__('catalog.buy'));
        $response->assertDontSee(__('catalog.notify'));
    }

    public function test_authenticated_user_sees_price_and_buy_button(): void
    {
        Product::factory()->create(['brand' => 'Есть В Наличии', 'price' => 1234, 'in_stock' => true]);

        $response = $this->actingAs(User::factory()->create())->get(route('home'));

        $response->assertOk();
        $response->assertSee('₽ 1 234');
        $response->assertSee(__('catalog.buy'));
    }

    public function test_product_card_title_uses_brand(): void
    {
        $manufacturer = Manufacturer::factory()->create(['name' => 'Big Village']);

        Product::factory()->create([
            'name' => 'Big Village "OLD SONG" (DDH NEDIPA) алк. 8,5% / Хейзи Дабл Индиа Пейл Эль, ж/б 0,45л',
            'brand' => 'OLD SONG',
            'manufacturer_id' => $manufacturer->id,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('OLD SONG');
        // Старая склейка "производитель · марка" в одной строке не должна вернуться.
        $response->assertDontSee('Big Village · OLD SONG');
    }

    public function test_product_card_title_falls_back_to_name_without_brand(): void
    {
        $product = Product::factory()->create(['name' => 'Без Марки', 'brand' => null]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee($product->name);
    }

    public function test_product_card_renders_placeholder_without_image(): void
    {
        $container = Container::factory()->create(['code' => 'can']);
        $volume = Volume::factory()->create(['label' => '0,45 л']);

        Product::factory()->create([
            'container_id' => $container->id,
            'volume_id' => $volume->id,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('data-container="can"', false);
        $response->assertSee('0,45 л');
    }

    public function test_product_card_placeholder_falls_back_without_container(): void
    {
        Product::factory()->create(['container_id' => null]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('data-container="none"', false);
    }

    public function test_products_of_inactive_category_are_hidden_without_any_filter(): void
    {
        $inactiveCategory = Category::factory()->create(['is_active' => false]);
        $hidden = Product::factory()->create(['name' => 'Товар Скрытой Категории', 'category_id' => $inactiveCategory->id]);

        $activeCategory = Category::factory()->create();
        $shown = Product::factory()->create(['name' => 'Товар Активной Категории', 'category_id' => $activeCategory->id]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee($hidden->name);
        $response->assertSee($shown->name);
    }

    public function test_products_of_inactive_category_stay_hidden_even_when_explicitly_filtered(): void
    {
        $inactiveCategory = Category::factory()->create(['is_active' => false]);
        $hidden = Product::factory()->create(['name' => 'Явно Запрошенная Скрытая Категория', 'category_id' => $inactiveCategory->id]);

        $response = $this->get(route('home', ['categories' => [$inactiveCategory->id]]));

        $response->assertOk();
        $response->assertDontSee($hidden->name);
    }

    public function test_products_of_inactive_manufacturer_are_hidden(): void
    {
        $inactiveManufacturer = Manufacturer::factory()->create(['is_active' => false]);
        $hidden = Product::factory()->create(['name' => 'Товар Скрытого Производителя', 'manufacturer_id' => $inactiveManufacturer->id]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee($hidden->name);
    }

    public function test_products_without_a_manufacturer_stay_visible(): void
    {
        $shown = Product::factory()->create(['name' => 'Товар Без Производителя', 'manufacturer_id' => null]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee($shown->name);
    }

    public function test_products_without_beer_details_stay_visible_when_no_beer_filter_applied(): void
    {
        // Регрессия: регистрация BeerStyleFilter/AbvRangeFilter/IbuRangeFilter сама по себе
        // не должна резать каталог — эти фильтры вызываются на каждом запросе (FilterManager
        // прогоняет все фильтры всегда), просто с пустыми параметрами.
        $shown = Product::factory()->create(['name' => 'Аксессуар Без Пивных Данных']);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee($shown->name);
    }

    public function test_beer_style_filter_narrows_results(): void
    {
        $wantedStyle = BeerStyle::factory()->create();
        $otherStyle = BeerStyle::factory()->create();

        $wanted = Product::factory()->create(['name' => 'Нужный Стиль']);
        BeerProductDetail::factory()->create(['product_id' => $wanted->id, 'beer_style_id' => $wantedStyle->id]);

        $other = Product::factory()->create(['name' => 'Другой Стиль']);
        BeerProductDetail::factory()->create(['product_id' => $other->id, 'beer_style_id' => $otherStyle->id]);

        $response = $this->get(route('home', ['beer_styles' => [$wantedStyle->id]]));

        $response->assertOk();
        $response->assertSee($wanted->name);
        $response->assertDontSee($other->name);
    }

    public function test_abv_and_ibu_range_filters_narrow_results(): void
    {
        $light = Product::factory()->create(['name' => 'Лёгкое Пиво']);
        BeerProductDetail::factory()->create(['product_id' => $light->id, 'abv' => 3.5, 'ibu' => 15]);

        $strong = Product::factory()->create(['name' => 'Крепкое Пиво']);
        BeerProductDetail::factory()->create(['product_id' => $strong->id, 'abv' => 9.0, 'ibu' => 70]);

        $response = $this->get(route('home', ['abv_min' => 5, 'abv_max' => 12, 'ibu_min' => 50, 'ibu_max' => 100]));

        $response->assertOk();
        $response->assertSee($strong->name);
        $response->assertDontSee($light->name);
    }

    public function test_beer_filters_hide_products_without_beer_details_when_explicitly_applied(): void
    {
        // Осмысленное поведение: "крепость от 5 до 7" не может матчить то, у чего
        // крепости вообще нет — в отличие от пустого запроса (тест выше), тут фильтр
        // применён явно, и товар без beerDetails обязан пропасть.
        $noBeerDetails = Product::factory()->create(['name' => 'Совсем Не Пиво']);

        $response = $this->get(route('home', ['abv_min' => 1]));

        $response->assertOk();
        $response->assertDontSee($noBeerDetails->name);
    }

    public function test_default_sort_shows_newest_products_first(): void
    {
        $older = Product::factory()->create(['brand' => 'Первый Импорт']);
        $newer = Product::factory()->create(['brand' => 'Второй Импорт']);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSeeInOrder([$newer->brand, $older->brand]);
    }

    public function test_sort_by_price_orders_ascending(): void
    {
        // sort=price_asc — гостю недоступен (SortFilter::availableCases()).
        Product::factory()->create(['brand' => 'Дорогое', 'price' => 4000]);
        Product::factory()->create(['brand' => 'Дешёвое', 'price' => 100]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('home', ['sort' => 'price_asc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Дешёвое', 'Дорогое']);
    }

    public function test_sort_by_price_orders_descending(): void
    {
        Product::factory()->create(['brand' => 'Дорогое', 'price' => 4000]);
        Product::factory()->create(['brand' => 'Дешёвое', 'price' => 100]);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('home', ['sort' => 'price_desc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Дорогое', 'Дешёвое']);
    }

    public function test_guest_sort_by_price_query_param_falls_back_to_default(): void
    {
        // Гость не может восстановить порядок цен через ?sort=price_asc —
        // SortFilter::apply() отклоняет недоступный кейс и уходит на дефолт.
        $older = Product::factory()->create(['brand' => 'Первый Импорт', 'price' => 4000]);
        $newer = Product::factory()->create(['brand' => 'Второй Импорт', 'price' => 100]);

        $response = $this->get(route('home', ['sort' => 'price_asc']));

        $response->assertOk();
        $response->assertSeeInOrder([$newer->brand, $older->brand]);
    }

    public function test_guest_does_not_see_price_sort_options(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee(ProductSort::PRICE_ASC->label());
        $response->assertDontSee(ProductSort::PRICE_DESC->label());
    }

    public function test_sort_by_brand_orders_alphabetically(): void
    {
        Product::factory()->create(['brand' => 'Брют']);
        Product::factory()->create(['brand' => 'Амбер']);

        $response = $this->get(route('home', ['sort' => 'brand_asc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Амбер', 'Брют']);
    }

    public function test_sort_by_brand_orders_reverse_alphabetically(): void
    {
        Product::factory()->create(['brand' => 'Брют']);
        Product::factory()->create(['brand' => 'Амбер']);

        $response = $this->get(route('home', ['sort' => 'brand_desc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Брют', 'Амбер']);
    }

    public function test_sort_by_manufacturer_orders_alphabetically(): void
    {
        $manufacturerB = Manufacturer::factory()->create(['name' => 'Пивоварня Б']);
        $manufacturerA = Manufacturer::factory()->create(['name' => 'Пивоварня А']);

        Product::factory()->create(['brand' => 'Товар Б', 'manufacturer_id' => $manufacturerB->id]);
        Product::factory()->create(['brand' => 'Товар А', 'manufacturer_id' => $manufacturerA->id]);
        // Товар без производителя не должен ломать сортировку — просто остаётся в выдаче.
        Product::factory()->create(['brand' => 'Без Производителя', 'manufacturer_id' => null]);

        $response = $this->get(route('home', ['sort' => 'manufacturer_asc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Товар А', 'Товар Б']);
        $response->assertSee('Без Производителя');
    }

    public function test_sort_by_manufacturer_orders_reverse_alphabetically(): void
    {
        $manufacturerB = Manufacturer::factory()->create(['name' => 'Пивоварня Б']);
        $manufacturerA = Manufacturer::factory()->create(['name' => 'Пивоварня А']);

        Product::factory()->create(['brand' => 'Товар Б', 'manufacturer_id' => $manufacturerB->id]);
        Product::factory()->create(['brand' => 'Товар А', 'manufacturer_id' => $manufacturerA->id]);

        $response = $this->get(route('home', ['sort' => 'manufacturer_desc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Товар Б', 'Товар А']);
    }

    public function test_sort_by_rating_orders_highest_first(): void
    {
        $lowRated = Product::factory()->create(['brand' => 'Низкий Рейтинг']);
        BeerProductDetail::factory()->create([
            'product_id' => $lowRated->id,
            'untappd_beer_id' => UntappdBeer::factory()->create(['rating_score' => 2.1])->id,
        ]);

        $highRated = Product::factory()->create(['brand' => 'Высокий Рейтинг']);
        BeerProductDetail::factory()->create([
            'product_id' => $highRated->id,
            'untappd_beer_id' => UntappdBeer::factory()->create(['rating_score' => 4.5])->id,
        ]);

        // Аксессуар без beerDetails — сортировка не должна вести себя как
        // whereHas-фильтр (тот же guard-принцип, что у ofBeerStyles()).
        Product::factory()->create(['brand' => 'Совсем Не Пиво']);

        $response = $this->get(route('home', ['sort' => 'rating_desc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Высокий Рейтинг', 'Низкий Рейтинг']);
        $response->assertSee('Совсем Не Пиво');
    }

    public function test_sort_by_rating_orders_lowest_first(): void
    {
        $lowRated = Product::factory()->create(['brand' => 'Низкий Рейтинг']);
        BeerProductDetail::factory()->create([
            'product_id' => $lowRated->id,
            'untappd_beer_id' => UntappdBeer::factory()->create(['rating_score' => 2.1])->id,
        ]);

        $highRated = Product::factory()->create(['brand' => 'Высокий Рейтинг']);
        BeerProductDetail::factory()->create([
            'product_id' => $highRated->id,
            'untappd_beer_id' => UntappdBeer::factory()->create(['rating_score' => 4.5])->id,
        ]);

        $response = $this->get(route('home', ['sort' => 'rating_asc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Низкий Рейтинг', 'Высокий Рейтинг']);
    }

    public function test_sort_by_style_orders_alphabetically(): void
    {
        $styleB = BeerStyle::factory()->create(['name' => 'Портер']);
        $styleA = BeerStyle::factory()->create(['name' => 'Лагер']);

        $productB = Product::factory()->create(['brand' => 'Товар Портер']);
        BeerProductDetail::factory()->create(['product_id' => $productB->id, 'beer_style_id' => $styleB->id]);

        $productA = Product::factory()->create(['brand' => 'Товар Лагер']);
        BeerProductDetail::factory()->create(['product_id' => $productA->id, 'beer_style_id' => $styleA->id]);

        $response = $this->get(route('home', ['sort' => 'style_asc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Товар Лагер', 'Товар Портер']);
    }

    public function test_sort_by_style_orders_reverse_alphabetically(): void
    {
        $styleB = BeerStyle::factory()->create(['name' => 'Портер']);
        $styleA = BeerStyle::factory()->create(['name' => 'Лагер']);

        $productB = Product::factory()->create(['brand' => 'Товар Портер']);
        BeerProductDetail::factory()->create(['product_id' => $productB->id, 'beer_style_id' => $styleB->id]);

        $productA = Product::factory()->create(['brand' => 'Товар Лагер']);
        BeerProductDetail::factory()->create(['product_id' => $productA->id, 'beer_style_id' => $styleA->id]);

        $response = $this->get(route('home', ['sort' => 'style_desc']));

        $response->assertOk();
        $response->assertSeeInOrder(['Товар Портер', 'Товар Лагер']);
    }

    public function test_invalid_sort_is_rejected_instead_of_500(): void
    {
        $response = $this->get(route('home', ['sort' => 'drop_table']));

        $response->assertInvalid(['sort']);
    }

    public function test_sort_select_auto_submits_on_change(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('x-on:change="$el.form.submit()"', false);
    }

    public function test_sort_is_preserved_in_the_next_page_url(): void
    {
        Product::factory()->count(30)->create();

        $response = $this->actingAs(User::factory()->create())->get(
            route('home', ['sort' => 'price_asc']),
            ['X-Catalog-Partial' => '1']
        );

        $response->assertOk();
        $this->assertStringContainsString('sort=price_asc', urldecode($response->headers->get('X-Next-Page')));
    }

    public function test_sorting_by_rating_does_not_trigger_n_plus_one_queries(): void
    {
        Manufacturer::factory()->count(3)->create()->each(function (Manufacturer $manufacturer): void {
            $product = Product::factory()->create(['manufacturer_id' => $manufacturer->id]);
            BeerProductDetail::factory()->create([
                'product_id' => $product->id,
                'untappd_beer_id' => UntappdBeer::factory()->create()->id,
            ]);
        });

        DB::enableQueryLog();
        $this->get(route('home', ['sort' => 'rating_desc']))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Коррелированный подзапрос в ORDER BY не добавляет запрос на строку —
        // тот же бюджет, что у обычного каталога без сортировки.
        $this->assertLessThan(15, $queryCount, 'Сортировка по рейтингу не должна добавлять N+1.');
    }

    public function test_partial_request_returns_only_cards_with_next_page_header(): void
    {
        Product::factory()->count(30)->create();

        $response = $this->get(route('home', ['page' => 2]), ['X-Catalog-Partial' => '1']);

        $response->assertOk();
        $response->assertDontSee('</header>', false);
        $response->assertDontSee('</footer>', false);
    }

    public function test_next_page_header_is_empty_on_the_last_page(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->get(route('home'), ['X-Catalog-Partial' => '1']);

        $response->assertOk();
        $this->assertSame('', $response->headers->get('X-Next-Page'));
    }

    public function test_next_page_header_carries_a_url_when_more_pages_exist(): void
    {
        Product::factory()->count(30)->create();

        $response = $this->get(route('home'), ['X-Catalog-Partial' => '1']);

        $response->assertOk();
        $nextPage = $response->headers->get('X-Next-Page');
        $this->assertNotSame('', $nextPage);
        $this->assertStringContainsString('page=2', $nextPage);
    }

    public function test_active_filters_are_preserved_in_the_next_page_url(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(30)->create(['category_id' => $category->id]);

        $response = $this->get(
            route('home', ['categories' => [$category->id]]),
            ['X-Catalog-Partial' => '1']
        );

        $response->assertOk();
        $this->assertStringContainsString('categories', urldecode($response->headers->get('X-Next-Page')));
    }

    public function test_invalid_category_filter_is_rejected_instead_of_500(): void
    {
        $response = $this->get(route('home', ['categories' => [999999]]));

        $response->assertInvalid(['categories.0']);
    }

    public function test_product_cards_do_not_trigger_n_plus_one_queries(): void
    {
        Manufacturer::factory()->count(3)->create()->each(function (Manufacturer $manufacturer): void {
            $volume = Volume::factory()->create();
            $container = Container::factory()->create();

            Product::factory()->count(5)->create([
                'manufacturer_id' => $manufacturer->id,
                'volume_id' => $volume->id,
                'container_id' => $container->id,
            ]);
        });

        DB::enableQueryLog();
        $this->get(route('home'))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Товары + справочники фильтров (категории/производители/объёмы/тара) —
        // фиксированное небольшое число запросов независимо от количества товаров.
        $this->assertLessThan(15, $queryCount, 'Ожидались фиксированные запросы без N+1 по manufacturer/volume/container.');
    }
}
