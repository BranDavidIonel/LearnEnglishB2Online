<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Course\PlacementPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The placement test: where in the course the learner starts. */
class PlacementController extends Controller
{
    public function __invoke(Request $request, PlacementPage $page): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Placement', $page->data($user));
    }
}
