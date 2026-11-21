<?php

namespace App\Http\Controllers;

use App\Enums\Game;
use App\Models\User;
use App\Services\Games\GameWords;
use App\Services\Progress\GameService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The word games: the list with the records, then one page per game with the words it plays with. */
class GameController extends Controller
{
    public function __invoke(Request $request, GameService $games, GameWords $words, ?string $game = null): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($game === null) {
            // An object even without any record yet, so the page always gets { memory?, sprint?, spell? }.
            return Inertia::render('games/Index', ['records' => (object) $games->records($user)]);
        }

        $chosen = Game::from($game);

        return Inertia::render(match ($chosen) {
            Game::Memory => 'games/Memory',
            Game::Sprint => 'games/Sprint',
            Game::Spell => 'games/Spell',
        }, ['words' => $words->for($user, $chosen)]);
    }
}
