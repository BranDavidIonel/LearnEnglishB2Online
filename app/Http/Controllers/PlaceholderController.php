<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stands in for the pages that are not built yet, so every route of the route map
 * exists (and its Wayfinder helper) from the start. Each area replaces it with its own
 * controller in its routes/web/<area>.php file.
 */
class PlaceholderController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Placeholder', [
            'page' => (string) $request->route()?->getName(),
        ]);
    }
}
