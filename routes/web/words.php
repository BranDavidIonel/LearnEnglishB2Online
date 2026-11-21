<?php

use App\Enums\Level;
use App\Enums\WordStatus;
use App\Http\Controllers\FlashcardController;
use App\Http\Controllers\WordBankController;
use App\Http\Controllers\WordController;
use App\Http\Controllers\WordSortController;
use Illuminate\Support\Facades\Route;

// Flashcards, the word lists, the quick sort of a level and the word bank of a unit.
Route::get('flashcards', FlashcardController::class)->name('flashcards');
Route::get('words/{tab?}', WordController::class)->whereIn('tab', [...WordStatus::values(), 'extra'])->name('words');
Route::get('words/sort/{level}', WordSortController::class)->whereIn('level', Level::values())->name('words.sort');
Route::get('units/{unit}/bank', WordBankController::class)->where('unit', '[a-z][0-9]-u[0-9]{2}')->name('units.bank');
