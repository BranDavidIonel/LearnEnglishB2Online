<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Home\CourseMapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The grammar of the whole course: the list of every unit's grammar, and one unit's lesson. */
class GrammarController extends Controller
{
    public function __invoke(Request $request, CourseMapService $map, ?string $unit = null): Response|RedirectResponse
    {
        if ($unit === null) {
            /** @var User $user */
            $user = $request->user();

            return Inertia::render('grammar/Index', ['levels' => $map->grammarList($user)]);
        }

        $lesson = $map->grammarLesson($unit);

        // The original showed the list for a unit it did not know.
        if ($lesson === null) {
            return redirect()->route('grammar');
        }

        return Inertia::render('grammar/Show', ['unit' => $lesson]);
    }
}
