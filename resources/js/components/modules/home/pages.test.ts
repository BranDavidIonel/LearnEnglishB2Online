// @vitest-environment happy-dom
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vite-plus/test';
import type {
    LearnLevel,
    NextStep,
    Road,
} from '@/components/modules/home/types';
import { trans } from '@/i18n';
import type { Replacements } from '@/i18n';
import Home from '@/pages/Home.vue';
import Learn from '@/pages/Learn.vue';
import type { LevelSummary } from '@/types';

const learner = {
    xp: 972,
    xpToday: 20,
    goal: 50,
    streak: 3,
    level: null,
    due: { words: 4, verbs: 0, mistakes: 2 },
};

const global = {
    mocks: {
        $t: (key: string, replacements?: Replacements) =>
            trans(key, replacements, 'en'),
        $page: { props: { locale: 'en' } },
        $learner: learner,
    },
    stubs: {
        Head: true,
        Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    },
};

function level(
    id: 'a1' | 'b1',
    patch: Partial<LevelSummary> = {},
): LevelSummary {
    return {
        id,
        name: id.toUpperCase(),
        title: id === 'a1' ? 'Beginner' : 'Intermediate',
        description: '',
        color: id === 'a1' ? '#22c55e' : '#a78bfa',
        index: id === 'a1' ? 0 : 2,
        units: 1,
        unlocked: true,
        done: false,
        passed: false,
        skipped: false,
        progress: 0.25,
        exam: null,
        ...patch,
    };
}

const road: Road = {
    achieved: null,
    current: 'A1',
    levels: [level('a1'), level('b1', { unlocked: false, progress: 0 })],
    goals: [],
};

function home(next: NextStep) {
    return mount(Home, {
        props: {
            next,
            wordsToLearn: null,
            road,
            week: [],
            counts: {
                words: { learned: 340, learning: 10, new: 50, total: 400 },
                verbs: { learned: 1, learning: 0, new: 182, total: 183 },
                placement: true,
            },
            passMark: 0.8,
        },
        global,
    });
}

describe('Home page', () => {
    it("shows the next step of a unit and today's numbers", () => {
        const page = home({
            kind: 'unit',
            level: { id: 'b1', name: 'B1', color: '#a78bfa' },
            unit: {
                id: 'b1-u04',
                order: 4,
                title: 'It had all gone wrong',
                titleRo: '',
                emoji: '🚨',
            },
            step: 'grammar',
            review: false,
        });

        expect(page.find('.hero').text()).toContain('Continue · B1 · Unit 4');
        expect(page.find('.hero').text()).toContain('Next step: Grammar');
        expect(page.find('.hero a.start').attributes('href')).toBe(
            '/units/b1-u04/grammar',
        );
        expect(page.find('.stat.pulse').text()).toContain(
            '4flashcards to review today',
        );
        expect(page.text()).toContain('My mistakes (2)');
        expect(page.text()).toContain('Retake the placement test');
        expect(page.findAll('.road-step')[1].classes()).toContain('locked');
        expect(page.findAll('.road-step')[1].text()).toContain('🔒');
    });

    it('offers the level exam with its pass mark', () => {
        const page = home({
            kind: 'exam',
            level: { id: 'b1', name: 'B1', color: '#a78bfa' },
            unit: null,
            step: null,
            review: false,
        });

        expect(page.find('.hero h2').text()).toBe(
            "You're ready for the B1 level exam!",
        );
        expect(page.find('.hero').text()).toContain('at least 80%');
        expect(page.find('.hero a').attributes('href')).toBe('/exams/b1');
    });
});

describe('Learn page', () => {
    it('draws open, passed, review and locked units and the exam card', () => {
        const unit = {
            order: 1,
            title: 'T',
            titleRo: 'R',
            emoji: '👋',
            levelId: 'a1' as const,
            index: 0,
            best: 0,
            tries: 0,
            steps: [],
            grammar: 'To be',
        };
        const levels: LearnLevel[] = [
            {
                level: level('a1'),
                units: [
                    {
                        ...unit,
                        id: 'a1-u01',
                        unlocked: true,
                        progress: 1,
                        passed: true,
                        review: false,
                    },
                    {
                        ...unit,
                        id: 'a1-u02',
                        unlocked: true,
                        progress: 0.5,
                        passed: true,
                        review: true,
                    },
                    {
                        ...unit,
                        id: 'a1-u03',
                        unlocked: false,
                        progress: 0,
                        passed: false,
                        review: false,
                    },
                ],
                words: { learned: 3, total: 30 },
                canLearnWords: true,
                review: 1,
            },
            {
                level: level('b1', { unlocked: false }),
                units: [
                    {
                        ...unit,
                        id: 'b1-u01',
                        unlocked: false,
                        progress: 0,
                        passed: false,
                        review: false,
                    },
                ],
                words: { learned: 0, total: 0 },
                canLearnWords: false,
                review: 0,
            },
        ];
        const page = mount(Learn, {
            props: { levels, batch: 20, passMark: 0.8 },
            global,
        });
        const cards = page.findAll('.unit-card');

        expect(cards[0].find('.unit-badge').text()).toBe('⭐');
        expect(cards[1].find('.unit-badge').text()).toBe('⚠️');
        expect(cards[1].text()).toContain('Needs review');
        expect(cards[2].element.tagName).toBe('DIV');
        expect(cards[2].attributes('title')).toBe(
            'Finish the previous unit first',
        );
        expect(cards[3].text()).toContain('First review 1 unit');
        expect(page.find('.level-words').text()).toContain(
            'A1 words: 3 / 30 learned',
        );
        expect(page.find('.level-words a').text()).toBe(
            'learn them 20 at a time →',
        );
        expect(page.find('.locked-note').text()).toContain(
            'Pass the A1 level exam',
        );
        expect(page.findAll('section.level')[1].classes()).toContain('locked');
        expect(page.findAll('.chip-tag')[1].text()).toBe('🔒 Locked');
    });
});
