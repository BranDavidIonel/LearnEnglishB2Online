<?php

use App\Enums\Game;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

// The word games: the list, then one page per game.
Route::get('games/{game?}', GameController::class)->whereIn('game', Game::values())->name('games');
