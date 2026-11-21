<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * The data every page gets (typed in resources/js/types/global.d.ts, "sharedPageProps").
 *
 * Flash messages are not a prop: a controller calls Inertia::flash('success', $text)
 * (or 'error') and the page reads them from $page.flash (typed as "flashDataType").
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Interface language (set by SetLocale) and the ones the switch offers.
            'locale' => app()->getLocale(),
            'locales' => config('learning.locales'),
            'auth' => [
                // Null on the login page.
                'user' => fn () => $request->user()?->only(['id', 'name', 'email']),
            ],
        ];
    }
}
