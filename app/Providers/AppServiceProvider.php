<?php

namespace App\Providers;

use Domain\Cart\Providers\CartServiceProvider;
use Domain\Catalog\Filters\AbvRangeFilter;
use Domain\Catalog\Filters\BeerStyleFilter;
use Domain\Catalog\Filters\CategoryFilter;
use Domain\Catalog\Filters\ContainerFilter;
use Domain\Catalog\Filters\FilterManager;
use Domain\Catalog\Filters\FilterOptionsRegistry;
use Domain\Catalog\Filters\IbuRangeFilter;
use Domain\Catalog\Filters\InStockFilter;
use Domain\Catalog\Filters\ManufacturerFilter;
use Domain\Catalog\Filters\PriceRangeFilter;
use Domain\Catalog\Filters\SearchFilter;
use Domain\Catalog\Filters\SortFilter;
use Domain\Catalog\Filters\VolumeFilter;
use Domain\Content\Actions\Content\LoadSiteFooter;
use Domain\Content\Actions\Content\LoadSiteMenu;
use Domain\Favorite\Providers\FavoriteServiceProvider;
use Domain\Order\Providers\OrderServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Services\CatalogImport\CategoryRegistry;
use Services\Untappd\Providers\UntappdProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Telegram\Provider as TelegramProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(
            UntappdProvider::class
        );

        $this->app->register(
            FavoriteServiceProvider::class
        );

        $this->app->register(
            CartServiceProvider::class
        );

        $this->app->register(
            OrderServiceProvider::class
        );

        // Illuminate\Pipeline\Pipeline::carry() re-make()s every stage per
        // CSV row, so a transient CategoryRegistry would re-run its
        // Cache::rememberForever() lookup thousands of times per import.
        // Singleton + explicit CategoryRegistry::flush() (see that class)
        // keeps it to one lookup per process/until invalidated.
        $this->app->singleton(CategoryRegistry::class);

        // Тот же мотив, что у CategoryRegistry выше — singleton +
        // forgetInstance() в flush() (см. FilterOptionsRegistry), не голая
        // static-переменная.
        $this->app->singleton(FilterOptionsRegistry::class);

        $this->app->singleton(FilterManager::class);

        // Singleton, не readonly-объект per-request — LoadSiteMenu
        // мемоизирует построенное дерево (см. класс), так что View Composer
        // ниже и LoadPublicPage::handle() на home/about не строят его дважды.
        $this->app->singleton(LoadSiteMenu::class);

        // Тот же мотив, что у LoadSiteMenu — мемоизация на запрос под
        // composer подвала ниже.
        $this->app->singleton(LoadSiteFooter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Регистрация telegram-драйвера Socialite (форма для Laravel 11+, см.
        // README пакета). Живёт здесь, а не в FortifyServiceProvider: Socialite
        // от Fortify не зависит, это два независимых механизма входа.
        // config('services.telegram.*') заполняется НЕ здесь. boot() всех
        // провайдеров отрабатывает на каждый запрос ещё до того, как
        // гарантированно применены миграции (ломает свежую БД/тесты —
        // TelegramBot::current() кинул бы QueryException на
        // ещё не существующую telegraph_bots) и до того, как маршруты
        // гарантированно загружены. Единственное место, которому эти
        // значения реально нужны, — TelegramLoginController::callback()
        // (там же и заполняются, локально и лениво). Кнопка виджета тоже
        // не читает config() — берёт username прямо у TelegramBot::current()
        // (см. <x-ui.telegram-login-button>).
        Event::listen(fn (SocialiteWasCalled $event) => $event->extendSocialite(
            'telegram',
            TelegramProvider::class,
        ));

        // Порядок = визуальный порядок в pages/catalog/_filters.blade.php.
        app(FilterManager::class)->registerFilters([
            new SortFilter,
            new SearchFilter,
            new InStockFilter,
            new CategoryFilter,
            new BeerStyleFilter,
            new ManufacturerFilter,
            new VolumeFilter,
            new ContainerFilter,
            new PriceRangeFilter,
            new AbvRangeFilter,
            new IbuRangeFilter,

        ]);

        // $menu доступен в шапке на ЛЮБОЙ публичной странице, а не только
        // на home/about (единственных, чьи контроллеры зовут LoadPublicPage
        // напрямую) — прокидывать проп через каждый контроллер/view ради
        // одинаковых на всём сайте данных было бы избыточно. <x-ui.mobile-nav>
        // (нижняя мобильная панель) к CMS-меню намеренно не привязана — её
        // ссылки хардкожены отдельно, composer её не задевает. На
        // <x-layouts.auth> (нет шапки) и в MoonShine composer не
        // срабатывает вовсе, лишних запросов там нет.
        View::composer(
            'components.layouts.header',
            fn ($view) => $view->with('menu', app(LoadSiteMenu::class)->handle()),
        );

        // Подвал — то же самое, что и меню выше, но за одним блоком
        // глобальной секции `footer` вместо дерева. $block может быть null
        // (секция/блок ещё не заведены в CMS) — footer.blade.php читает
        // content null-safe.
        View::composer(
            'components.layouts.footer',
            fn ($view) => $view->with('block', app(LoadSiteFooter::class)->handle()),
        );
    }
}
