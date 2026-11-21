<?php

use App\Http\Controllers\PlaceholderController;
use Illuminate\Support\Facades\Route;

// Theme, voice, sound, daily goal, export / import and reset of the progress.
Route::get('settings', PlaceholderController::class)->name('settings');
