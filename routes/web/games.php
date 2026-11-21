<?php

use App\Enums\Game;
use App\Http\Controllers\PlaceholderController;
use Illuminate\Support\Facades\Route;

// The word games: the list, then one page per game.
Route::get('games/{game?}', PlaceholderController::class)->whereIn('game', Game::values())->name('games');
