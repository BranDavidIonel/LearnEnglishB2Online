<?php

namespace App\Http\Controllers;

use App\Enums\UnitStep;
use App\Models\User;
use App\Services\Course\UnitPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A unit: its overview and its six steps. A unit that is not in the course or not open
 * yet shows the same screens as the original ("this unit doesn't exist", "unit locked").
 */
class UnitController extends Controller
{
    public function __construct(private readonly UnitPage $page) {}

    public function show(Request $request, string $unit): Response
    {
        $user = $this->learner($request);

        return $this->unavailable($user, $unit) ?? Inertia::render('units/Show', $this->page->overview($user, $unit));
    }

    public function step(Request $request, string $unit, string $step): Response
    {
        $user = $this->learner($request);

        return $this->unavailable($user, $unit) ?? Inertia::render('units/Step', $this->page->step($user, $unit, UnitStep::from($step)));
    }

    private function unavailable(User $user, string $unit): ?Response
    {
        $reason = $this->page->blocked($user, $unit);

        return $reason === null ? null : Inertia::render('units/Unavailable', ['reason' => $reason]);
    }

    private function learner(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
