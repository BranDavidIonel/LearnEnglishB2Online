<?php

use App\Http\Controllers\VerbController;
use Illuminate\Support\Facades\Route;

// The irregular verbs (list, drills, review).
Route::get('words/verbs', VerbController::class)->name('verbs');
