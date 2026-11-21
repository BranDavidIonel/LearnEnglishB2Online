<?php

use App\Http\Controllers\PlaceholderController;
use Illuminate\Support\Facades\Route;

// Cambridge mock exams: the exams, one exam, one test, one paper of a test.
Route::get('cambridge/{exam?}/{test?}/{paper?}', PlaceholderController::class)
    ->where('exam', '[a-z0-9]+')
    ->where('test', '[a-z0-9-]+')
    ->whereIn('paper', ['reading', 'listening', 'writing', 'speaking'])
    ->name('cambridge');
