// The learner state shared by the whole app: the XP, streak, level and menu badges of the
// top bar ($learner) and the settings ($settings). The layout fills it from the shared page
// props on every visit and @/utils/http from every answer of the progress API, so the top
// bar and the badges follow what the learner does without reloading the page.
import { reactive } from 'vue';
import type { App } from 'vue';
import type { Learner, Settings } from '@/types';

/** Before the first page arrives: a learner with nothing done yet and the default settings. */
export const learnerState = reactive<{ learner: Learner; settings: Settings }>({
    learner: {
        xp: 0,
        xpToday: 0,
        goal: 50,
        streak: 0,
        level: null,
        due: { words: 0, verbs: 0, mistakes: 0 },
    },
    settings: { theme: 'dark', rate: 0.9, voice: null, sound: true },
});

export function setLearner(learner: Learner): void {
    learnerState.learner = learner;
}

/** Keeps the settings and puts their theme on <html> at once (the style sheet reads data-theme). */
export function setSettings(settings: Settings): void {
    learnerState.settings = settings;

    if (typeof document !== 'undefined') {
        document.documentElement.dataset.theme = settings.theme;
    }
}

/** Adds `$learner` and `$settings` to every component (templates and `this.` in the Options API). */
export const learner = {
    install(app: App): void {
        Object.defineProperty(app.config.globalProperties, '$learner', {
            get: (): Learner => learnerState.learner,
        });
        Object.defineProperty(app.config.globalProperties, '$settings', {
            get: (): Settings => learnerState.settings,
        });
    },
};
