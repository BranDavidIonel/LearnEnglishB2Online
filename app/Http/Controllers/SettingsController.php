<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The settings page. Everything it shows is already shared with every page (settings, learner
 * goal, the signed-in account); its changes go through the progress API and AccountController.
 */
class SettingsController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Settings');
    }
}
