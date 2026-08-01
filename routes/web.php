<?php

use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\PageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');

Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::resource('addresses', AddressController::class)->except(['show']);
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
