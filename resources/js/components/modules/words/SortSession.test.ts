// @vitest-environment happy-dom
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { addXp, gradeWord, learnWord, sortedKnown } from '@/api/progress';
import ExerciseSession from '@/components/exercise/ExerciseSession.vue';
import { trans } from '@/i18n';
import type { Replacements } from '@/i18n';
import type { McExercise } from '@/types';
import { HttpError } from '@/utils/http';
import { toastState } from '@/utils/toast';
import SortSession from './SortSession.vue';
import type { SortWord } from './types';

vi.mock('@/api/progress', () => ({
    addXp: vi.fn(() => Promise.resolve({})),
    gradeWord: vi.fn(() => Promise.resolve({ words: [] })),
    learnWord: vi.fn(() => Promise.resolve({ words: [] })),
    sortedKnown: vi.fn(() => Promise.resolve({ words: [] })),
}));
vi.mock('@/utils/speech', () => ({ speak: vi.fn(), stopSpeak: vi.fn() }));
vi.mock('@/utils/sound', () => ({ sfx: vi.fn() }));
vi.mock('@/utils/confetti', () => ({ confetti: vi.fn() }));

function word(en: string, ro: string): SortWord {
    return {
        en,
        ro,
        pos: 'noun',
        ipa: '/x/',
        emoji: '📘',
        ex: `A ${en}.`,
        exRo: `Un ${ro}.`,
        unit: 'a1-u01',
        levelId: 'a1',
        bank: true,
        status: 'new',
        box: null,
        due: null,
        known: false,
        options: ['pisică', ro, 'casă', 'copac'],
    };
}

const WORDS = [word('dog', 'câine'), word('table', 'masă')];

let wrapper: VueWrapper | null = null;

function start(): VueWrapper {
    wrapper = mount(SortSession, {
        props: { words: WORDS, title: 'Bank' },
        global: {
            mocks: {
                $t: (key: string, replacements?: Replacements) =>
                    trans(key, replacements, 'en'),
            },
            stubs: { ExerciseSession: true, WordPicture: true },
        },
        attachTo: document.body,
    });

    return wrapper;
}

const press = (key: string): void => {
    document.dispatchEvent(new KeyboardEvent('keydown', { key }));
};

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

describe('SortSession', () => {
    it('sorts the cards, asks the new words and gives known + 3 × right first time', async () => {
        const session = start();

        expect(session.find('.sess-count').text()).toBe('1 / 2');
        press('1');
        expect(sortedKnown).not.toHaveBeenCalled();

        press(' ');
        await flushPromises();
        expect(session.find('.fc').classes()).toContain('flipped');
        press('1');
        press(' ');
        press('2');
        await flushPromises();

        expect(sortedKnown).toHaveBeenCalledWith('dog');
        expect(learnWord).toHaveBeenCalledWith('table');

        const quiz = session.findComponent(ExerciseSession);
        const items = quiz.props('items') as { key: string; ex: McExercise }[];
        expect(items).toHaveLength(1);
        expect(items[0].key).toBe('table');
        expect(items[0].ex.q).toBe('What does **table** mean?');
        expect(items[0].ex.options[items[0].ex.answer]).toBe('masă');

        quiz.vm.$emit('finish', [
            { ex: items[0].ex, key: 'table', correct: true },
        ]);
        await flushPromises();

        expect(gradeWord).toHaveBeenCalledWith(['table'], 2);
        expect(addXp).toHaveBeenCalledWith(4);
        expect(session.text()).toContain('Batch done!');
        expect(session.text()).toContain('✓ 1 word you knew');
        expect(session.text()).toContain('(1 right first time)');
        expect(session.text()).toContain('+4 XP ⚡');
        expect(session.emitted('saved')).toHaveLength(1);
    });

    it('leaves without anything to save when no word was sorted', async () => {
        const session = start();

        await session.find('.sess-top .icon-btn').trigger('click');

        expect(session.emitted('exit')).toHaveLength(1);
        expect(addXp).not.toHaveBeenCalled();
    });

    it('keeps what was sorted when the quiz is left', async () => {
        const session = start();

        press(' ');
        press('2');
        press(' ');
        press('2');
        await flushPromises();
        session.findComponent(ExerciseSession).vm.$emit('exit');
        await flushPromises();

        expect(gradeWord).not.toHaveBeenCalled();
        expect(addXp).not.toHaveBeenCalled();
        expect(session.text()).toContain(
            '🧠 2 new words went into your flashcards.',
        );
        expect(session.emitted('saved')).toHaveLength(1);
    });

    it('stops at a save that fails instead of showing a summary', async () => {
        vi.mocked(sortedKnown).mockRejectedValueOnce(
            new HttpError(500, 'Server Error'),
        );
        const session = start();

        press(' ');
        press('1');
        await flushPromises();

        expect(session.text()).toContain('Not everything was saved');
        expect(session.find('.fc').exists()).toBe(false);
        expect(toastState.toasts.map((toast) => toast.message)).toContain(
            'Your progress could not be saved. Check the connection.',
        );
        press(' ');
        press('2');
        expect(learnWord).not.toHaveBeenCalled();
        expect(addXp).not.toHaveBeenCalled();
        expect(session.emitted('saved')).toHaveLength(1);
    });
});
