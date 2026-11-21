// Tiny Laravel-style translator for Vue.
// The key is the English text (like Laravel's __('...')), so English needs no dictionary.
// The Romanian dictionary is the merge of:
//   - lang/ro.json: shared with the backend (__() in PHP), framework texts included;
//   - resources/js/i18n/ro/*.json: front-end texts, one file per part of the app
//     (auth.json, layout.json, …), so each part can be translated on its own.
// A key must live in one file only (tests/Unit/TranslationsTest.php checks it).
// When Romanian says one English text in two ways (the original "Videouri" on the home page,
// "Video" in the menu), the second use gets a context in brackets at the end of its key:
// $t('Videos [shortcut]'). English never shows the context.
import { usePage } from '@inertiajs/vue3';
import type { App, ComponentPublicInstance } from 'vue';
import shared from '../../../lang/ro.json';

export type Replacements = Record<string, string | number>;

type Dictionary = Record<string, string>;

const romanianFiles = import.meta.glob<Dictionary>('./ro/*.json', {
    eager: true,
    import: 'default',
});

const messages: Record<string, Dictionary> = {
    ro: Object.assign({}, shared, ...Object.values(romanianFiles)),
};

/** " [shortcut]" at the end of a key. */
const CONTEXT = / \[[^\]]+\]$/;

let currentLocale = 'en';

/** Called by the layout whenever the server sends a (new) locale. */
export function setLocale(locale: string): void {
    currentLocale = locale;
}

export function getLocale(): string {
    return currentLocale;
}

/**
 * The locale for dates and numbers in the interface language, as in the original app:
 * `n.toLocaleString(intlLocale())`, `new Intl.DateTimeFormat(intlLocale(), …)`.
 */
export function intlLocale(locale: string = currentLocale): string {
    return locale === 'ro' ? 'ro-RO' : 'en-GB';
}

/**
 * The language of the current page. Reading the page props inside a template,
 * a computed() or a watcher makes them update when the language changes.
 */
function pageLocale(): string {
    const locale: unknown = usePage().props?.locale;

    return typeof locale === 'string' ? locale : currentLocale;
}

/**
 * Translates "Unit :number is done." with { number: 3 }. Unknown keys stay in English.
 * Works anywhere: templates ($t), the Options API (this.$t) and <script setup> / composables
 * (import { trans } from '@/i18n'), where it follows the page language by itself.
 */
export function trans(
    key: string,
    replacements: Replacements = {},
    locale: string = pageLocale(),
): string {
    let text = messages[locale]?.[key] ?? key.replace(CONTEXT, '');

    // "1 word|:count words": the first form for a count of 1, the last one otherwise.
    if (text.includes('|') && typeof replacements.count === 'number') {
        const forms = text.split('|');
        text = replacements.count === 1 ? forms[0] : forms[forms.length - 1];
    }

    // Longest names first, so ":count" never eats the start of ":countries".
    const names = Object.keys(replacements).sort((a, b) => b.length - a.length);

    for (const name of names) {
        text = text.replaceAll(`:${name}`, String(replacements[name]));
    }

    return text;
}

/** Adds `$t()` to every component (templates and `this.$t()` in the Options API). */
export const i18n = {
    install(app: App): void {
        app.config.globalProperties.$t = function (
            this: ComponentPublicInstance,
            key: string,
            replacements?: Replacements,
        ): string {
            // Reading $page makes every translated text re-render when the language changes.
            return trans(key, replacements, String(this.$page.props.locale));
        };
    },
};
