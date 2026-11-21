<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Progress\HistoryService;
use App\Services\Progress\LearnerSummary;
use App\Services\Progress\SettingsService;
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
            // The top bar and the menu badges (types/models.ts "Learner"), null on the login page.
            'learner' => fn () => $this->learner($request->user()),
            // Theme, speech and sound (types/models.ts "Settings"), null on the login page.
            'settings' => fn () => $request->user() ? app(SettingsService::class)->settings($request->user()) : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function learner(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        // The first page of a day puts a point on the progress chart, like opening the original app.
        app(HistoryService::class)->touch($user);

        return app(LearnerSummary::class)->for($user);
    }
}
