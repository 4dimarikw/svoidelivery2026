<?php

use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\FavoriteController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Auth\TelegramLoginController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('home');
Route::get('about', [PageController::class, 'about'])->name('about');

// Self-healing slug-биндинг (Domain\Catalog\Models\Product::getSlugOptions())
// резолвит {product} сам — {product:slug} тут не нужен, см. HasSlug::resolveRouteBinding().
Route::get('product/{product}', [ProductController::class, 'show'])->name('product.show');

// Точка возврата Telegram Login Widget — только вход/регистрация гостя.
// Привязка Telegram к уже авторизованному аккаунту через этот роут не идёт
// (см. комментарий у DELETE account/telegram ниже) — см. TelegramLoginController.
Route::get('auth/telegram/callback', [TelegramLoginController::class, 'callback'])
    ->middleware('throttle:10,1')
    ->name('auth.telegram.callback');

Route::middleware(['auth', 'verified'])->prefix('account')->name('account.')->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    // Привязка Telegram к уже авторизованному аккаунту НЕ роут этого
    // приложения — пользователь открывает t.me/<bot>?start=<code> в самом
    // Telegram, а обрабатывает это webhook-роут пакета defstudio/telegraph
    // (POST /telegraph/{token}/webhook, App\Telegraph\WebhookHandler::start()).
    Route::delete('telegram', [TelegramLoginController::class, 'unlink'])->name('telegram.unlink');

    Route::resource('addresses', AddressController::class)->except(['show']);

    Route::get('orders', [AccountOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [AccountOrderController::class, 'show'])->name('orders.show');

    Route::get('favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('favorites/{product}', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::delete('favorites', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
});

// Корзина — не часть личного кабинета, отдельная самостоятельная страница
// (Domain\Cart), поэтому вне prefix('account')/account-nav — но всё ещё
// только для авторизованных, middleware не меняется.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('cart/{product}', [CartController::class, 'increase'])->name('cart.increase');
    Route::patch('cart/{product}', [CartController::class, 'decrease'])->name('cart.decrease');
    Route::delete('cart/{product}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::delete('cart', [CartController::class, 'clear'])->name('cart.clear');

    // Оформление — тот же принцип, что корзина: не часть личного кабинета
    // (Domain\Order), поэтому вне prefix('account'). История уже оформленных
    // заказов — account.orders.* выше.
    Route::get('checkout', [OrderController::class, 'index'])->name('checkout.index');
    Route::post('checkout', [OrderController::class, 'store'])->name('checkout.store');
});

// Local-only preview of the <x-ui.*> component library — proves the value
// contract (Blade/old()/$errors vs x-model) works end-to-end without
// laravel/fortify installed. See CLAUDE.md.
if (app()->environment('local')) {
    Route::view('/ui-kit', 'ui-kit')->name('ui-kit');

    Route::post('/ui-kit', function (Request $request) {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
            'agree' => ['accepted'],
            'city' => ['required'],
        ]);

        return back()->with('status', 'ok');
    })->name('ui-kit.submit');
}
