<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The settings page. Besides the daily goals to choose from, everything it shows is already
 * shared with every page (settings, learner goal, the signed-in account); its changes go
 * through the progress API and AccountController.
 */
class SettingsController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Settings', [
            'goals' => config('learning.rules.goals'),
        ]);
    }
}
