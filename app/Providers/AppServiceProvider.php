<?php

namespace App\Providers;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Services\Content\ContentFiles;
use App\Services\Content\ContentRepository;
use App\Services\Progress\LearnerState;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The course content: read once, kept in memory for the request and in the cache.
        $this->app->singleton(ContentRepository::class, fn (Application $app): ContentRepository => new ContentRepository(
            new ContentFiles((string) config('learning.content_path')),
            $app->make(CacheManager::class)->store(config('learning.content_cache.store')),
            (int) config('learning.content_cache.ttl'),
        ));

        // The learner's progress rows, shared by the progress services for one request.
        $this->app->scoped(LearnerState::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Nothing of a learner's progress is kept from one request to the next (tests and
        // long-running servers handle many requests in one process).
        Event::listen(RequestHandled::class, fn () => $this->app->make(LearnerState::class)->flush());

        // The interface language the learner chose comes back at the login (unless one was
        // just picked on the login page).
        Event::listen(Login::class, function (Login $event): void {
            $locale = $event->user instanceof User ? $event->user->locale : null;

            if ($locale !== null && in_array($locale, config('learning.locales'), true) && ! session()->has(SetLocale::SESSION)) {
                session()->put(SetLocale::SESSION, $locale);
            }
        });

        // Sign-in attempts: 5 a minute per email and IP (routes/web.php, "throttle:login").
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Inertia props get plain objects instead of { data: {...} }.
        JsonResource::withoutWrapping();

        // Catch N+1 queries and typos in attribute names while developing.
        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
