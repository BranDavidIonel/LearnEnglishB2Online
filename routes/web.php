<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

// Language switch (EN / RO): also used on the login page.
Route::put('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    /*
     * Every part of the app has its own route file in routes/web/ (home.php, …), loaded
     * here in alphabetical order, so each one can be worked on without touching this file.
     * Every route in them needs the login.
     */
    $files = glob(__DIR__.'/web/*.php') ?: [];
    sort($files);

    foreach ($files as $file) {
        require $file;
    }
});
