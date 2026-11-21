// The colour themes, in the order the theme button cycles through them (App\Enums\Theme).
// The colours themselves live in resources/css/styles/tokens.css.
import type { ThemeId } from '@/types';

export interface ThemeInfo {
    id: ThemeId;
    /** English name, a translation key: $t(theme.name). */
    name: string;
    /** Page background, panel and accent: the three colours of the preview on the Settings page. */
    swatch: [string, string, string];
}

export const THEMES: ThemeInfo[] = [
    { id: 'dark', name: 'Dark', swatch: ['#0b1020', '#151c36', '#6366f1'] },
    {
        id: 'slate',
        name: 'Slate (softer)',
        swatch: ['#1b1e26', '#272c38', '#7c83f5'],
    },
    { id: 'light', name: 'Light', swatch: ['#f6f7fb', '#ffffff', '#4f46e5'] },
    {
        id: 'sepia',
        name: 'Sepia (warm)',
        swatch: ['#f3ead6', '#fbf5e6', '#a0521d'],
    },
];

/** The theme with this id; the first one (dark) for an unknown id. */
export function themeInfo(id: string): ThemeInfo {
    return THEMES.find((theme) => theme.id === id) ?? THEMES[0];
}

/** The theme after this one, back to the first after the last. */
export function nextTheme(id: string): ThemeInfo {
    const index = THEMES.findIndex((theme) => theme.id === id);

    return THEMES[(index + 1) % THEMES.length];
}
