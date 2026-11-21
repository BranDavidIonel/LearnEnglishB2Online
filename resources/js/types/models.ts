/** The signed-in account ($page.props.auth.user). */
export interface AuthUser {
    id: number;
    name: string;
    email: string;
}

/** The four levels of the course, in order (App\Enums\Level). */
export type LevelId = 'a1' | 'a2' | 'b1' | 'b2';

/** The four colour themes (App\Enums\Theme), applied as data-theme on <html>. */
export type ThemeId = 'dark' | 'slate' | 'light' | 'sepia';

/** What the top bar and the menu badges show ($page.props.learner, $learner). */
export interface Learner {
    /** Total XP. */
    xp: number;
    xpToday: number;
    /** Daily XP goal. */
    goal: number;
    /** Days in a row, 0 when broken. */
    streak: number;
    /** The level being worked on. */
    level: { id: LevelId; name: string; color: string } | null;
    /** Menu badges. */
    due: { words: number; verbs: number; mistakes: number };
}

/** The learner's settings ($page.props.settings, $settings). */
export interface Settings {
    theme: ThemeId;
    /** Speech rate. */
    rate: number;
    /** Name of the speech-synthesis voice. */
    voice: string | null;
    /** Sound effects. */
    sound: boolean;
}
