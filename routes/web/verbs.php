<?php

use App\Http\Controllers\PlaceholderController;
use Illuminate\Support\Facades\Route;

// The irregular verbs (list, drills, review).
Route::get('words/verbs', PlaceholderController::class)->name('verbs');
