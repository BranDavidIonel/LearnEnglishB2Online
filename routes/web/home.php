<?php

use App\Http\Controllers\PlaceholderController;
use Illuminate\Support\Facades\Route;

// Home, the course map, the progress page, the grammar lessons and the videos.
Route::get('/', PlaceholderController::class)->name('home');
Route::get('learn', PlaceholderController::class)->name('learn');
Route::get('progress', PlaceholderController::class)->name('progress');
Route::get('grammar/{unit?}', PlaceholderController::class)->where('unit', '[a-z][0-9]-u[0-9]{2}')->name('grammar');
Route::get('videos', PlaceholderController::class)->name('videos');
