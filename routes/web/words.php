<?php

use App\Enums\Level;
use App\Enums\WordStatus;
use App\Http\Controllers\PlaceholderController;
use Illuminate\Support\Facades\Route;

// Flashcards, the word lists, the quick sort of a level and the word bank of a unit.
Route::get('flashcards', PlaceholderController::class)->name('flashcards');
Route::get('words/{tab?}', PlaceholderController::class)->whereIn('tab', WordStatus::values())->name('words');
Route::get('words/sort/{level}', PlaceholderController::class)->whereIn('level', Level::values())->name('words.sort');
Route::get('units/{unit}/bank', PlaceholderController::class)->where('unit', '[a-z][0-9]-u[0-9]{2}')->name('units.bank');
