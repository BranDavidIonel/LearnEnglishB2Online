<?php

namespace App\Http\Controllers;

use App\Http\Resources\Home\RoadResource;
use App\Models\User;
use App\Services\Home\DashboardService;
use App\Services\Home\ProgressPageService;
use App\Services\Progress\BadgeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The progress page: the chart, the road to B2, the totals and the achievements. */
class ProgressController extends Controller
{
    public function __invoke(Request $request, ProgressPageService $progress, DashboardService $dashboard, BadgeService $badges): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Progress', [
            'chart' => $progress->chart($user),
            'road' => new RoadResource($dashboard->road($user)),
            'stats' => $progress->stats($user),
            'badges' => $badges->badges($user),
        ]);
    }
}
