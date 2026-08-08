<?php

use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\FavoriteController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('home');
Route::get('about', [PageController::class, 'about'])->name('about');

// Self-healing slug-биндинг (Domain\Catalog\Models\Product::getSlugOptions())
// резолвит {product} сам — {product:slug} тут не нужен, см. HasSlug::resolveRouteBinding().
Route::get('product/{product}', [ProductController::class, 'show'])->name('product.show');

Route::middleware(['auth', 'verified'])->prefix('account')->name('account.')->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::resource('addresses', AddressController::class)->except(['show']);

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
