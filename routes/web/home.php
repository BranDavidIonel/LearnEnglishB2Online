<?php

use App\Http\Controllers\GrammarController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

// Home, the course map, the progress page, the grammar lessons and the videos.
Route::get('/', HomeController::class)->name('home');
Route::get('learn', LearnController::class)->name('learn');
Route::get('progress', ProgressController::class)->name('progress');
Route::get('grammar/{unit?}', GrammarController::class)->where('unit', '[a-z][0-9]-u[0-9]{2}')->name('grammar');
Route::get('videos', VideoController::class)->name('videos');
