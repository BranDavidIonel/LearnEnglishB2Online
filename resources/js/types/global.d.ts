import type { Directive } from 'vue';
import type { AuthUser } from './models';

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
    }
}

export * from './models';
