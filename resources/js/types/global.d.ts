import type { Directive } from 'vue';
import type { AuthUser, Learner, Settings } from './models';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        // Shared by App\Http\Middleware\HandleInertiaRequests on every page.
        sharedPageProps: {
            name: string;
            /** Interface language: 'en' or 'ro'. */
            locale: string;
            /** Languages offered by the switch, e.g. ['en', 'ro']. */
            locales: string[];
            auth: { user: AuthUser | null };
            /** The learner's settings (null on the login page). */
            settings: Settings | null;
            /** Top bar and menu badge numbers (null on the login page). */
            learner: Learner | null;
            [key: string]: unknown;
        };
        // Inertia::flash('success', …) in a controller, $page.flash on the page.
        flashDataType: {
            success?: string;
            error?: string;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        /** Translate an English UI text (the key), with :placeholders filled in. See resources/js/i18n. */
        $t: (
            key: string,
            replacements?: Record<string, string | number>,
        ) => string;
        /** XP, streak, level and menu badges, kept fresh by the layout and by send(). See resources/js/utils/learner.ts. */
        $learner: Learner;
        /** Theme, speech rate, voice and sound. See resources/js/utils/learner.ts. */
        $settings: Settings;
    }
}

export * from './models';
