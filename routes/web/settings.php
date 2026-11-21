<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// Theme, voice, sound, daily goal, export / import and reset of the progress, and the account.
Route::get('settings', SettingsController::class)->name('settings');
Route::put('settings/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
Route::put('settings/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
