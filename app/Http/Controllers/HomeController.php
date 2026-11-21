<?php

namespace App\Http\Controllers;

use App\Http\Resources\Home\RoadResource;
use App\Models\User;
use App\Services\Home\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Start page (the original dashboard): what to do next, the road to B2, the last days and the shortcuts. */
class HomeController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Home', [
            'next' => $dashboard->nextStep($user),
            'wordsToLearn' => $dashboard->wordsToLearn($user),
            'road' => new RoadResource($dashboard->road($user)),
            'week' => $dashboard->week($user),
            'counts' => $dashboard->counts($user),
            'passMark' => (float) config('learning.rules.pass'),
        ]);
    }
}
