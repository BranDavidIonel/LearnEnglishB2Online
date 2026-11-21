<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// Start page (placeholder, replaced by the real home page).
Route::get('/', HomeController::class)->name('home');
