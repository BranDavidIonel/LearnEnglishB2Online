<?php

use App\Http\Controllers\PlaceholderController;
use Illuminate\Support\Facades\Route;

// Speaking practice: the list of modes, then one mode (with its scenario or level as {arg}).
Route::get('speaking/{mode?}/{arg?}', PlaceholderController::class)
    ->whereIn('mode', ['talk', 'role', 'repeat', 'say', 'answer', 'pairs', 'sprint'])
    ->where('arg', '[A-Za-z0-9_-]+')
    ->name('speaking');
