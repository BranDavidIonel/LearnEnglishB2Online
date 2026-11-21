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
import { forgetWord, learnWord, lookupWord, markKnown } from '@/api/progress';
import MicPanel from '@/components/speech/MicPanel.vue';
import { trans } from '@/i18n';
import type { Replacements } from '@/i18n';
import type { WordLookup as Lookup, WordWithState } from '@/types';
import { HttpError } from '@/utils/http';
import { speak } from '@/utils/speech';
import WordLookup from './WordLookup.vue';

vi.mock('@/api/progress', () => ({
    lookupWord: vi.fn(),
    markKnown: vi.fn(),
    learnWord: vi.fn(),
    forgetWord: vi.fn(),
}));
vi.mock('@/utils/speech', () => ({ speak: vi.fn(), stopSpeak: vi.fn() }));
vi.mock('@/utils/sound', () => ({ sfx: vi.fn() }));
vi.mock('@/utils/toast', () => ({ toast: vi.fn() }));
vi.mock('@/utils/recognition', () => ({
    canListen: () => false,
    isMicError: () => false,
    listen: vi.fn(),
    MIC_FATAL: [],
}));
vi.mock('@/utils/recording', () => ({ canRecord: () => false }));

const LEARNING = {
    status: 'learning',
    box: 1,
    due: '2026-11-22',
    known: false,
};

function answer(partial: Partial<Lookup>): { lookup: Lookup } {
    return {
        lookup: {
            word: 'alert',
            found: true,
            source: 'course',
            base: 'alert',
            relation: null,
            entry: {
                en: 'alert',
                ro: 'alertă',
                ipa: '/əˈlɜːt/',
                pos: 'noun',
                ex: 'The alert woke me up.',
                exRo: 'Alerta m-a trezit.',
                emoji: '🚨',
                wiki: null,
                levelId: 'b1',
                unit: 'b1-u04',
            },
            state: { status: 'new', box: 0, due: null, known: false },
            verb: null,
            phrases: [],
            ...partial,
        } as Lookup,
    };
}

/** The answer of a word action: the headword with its new state. */
function saved(en: string, state: Partial<WordWithState>) {
    return Promise.resolve({
        learner: {},
        words: [{ en, box: null, due: null, known: false, ...state }],
    } as never);
}

let wrapper: VueWrapper | null = null;

async function open(
    word: string,
    sentence = '',
    lookup: Promise<{ lookup: Lookup }> | Error | null = null,
): Promise<VueWrapper> {
    if (lookup instanceof Error) {
        vi.mocked(lookupWord).mockRejectedValue(lookup);
    } else {
        vi.mocked(lookupWord).mockReturnValue(
            lookup ?? Promise.resolve(answer({ word })),
        );
    }
    wrapper = mount(WordLookup, {
        props: { word, sentence },
        global: {
            mocks: {
                $t: (key: string, replacements?: Replacements) =>
                    trans(key, replacements, 'en'),
            },
            stubs: { Link: { template: '<a><slot /></a>' } },
        },
        attachTo: document.body,
    });
    await flushPromises();

    return wrapper;
}

const modal = (): HTMLElement =>
    document.querySelector('.lookup') as HTMLElement;

const buttons = (): string[] =>
    [...modal().querySelectorAll('.lookup-state button')].map(
        (b) => b.textContent?.trim() ?? '',
    );

async function press(label: string): Promise<void> {
    const button = [...modal().querySelectorAll('button')].find(
        (b) => b.textContent?.trim() === label,
    );
    button?.click();
    await flushPromises();
}

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

describe('WordLookup', () => {
    it('opens with the word and its sound before the lookup answers', async () => {
        let answerLookup: (value: { lookup: Lookup }) => void = () => {};
        await open(
            'alert',
            'I got an alert.',
            new Promise((resolve) => (answerLookup = resolve)),
        );

        expect(modal().querySelector('.lookup-word')?.textContent).toBe(
            'alert',
        );
        expect(speak).toHaveBeenCalledWith('alert', { slow: false });
        expect(lookupWord).toHaveBeenCalledWith('alert', 'I got an alert.');
        expect(modal().querySelector('.lookup-wait')).not.toBeNull();

        answerLookup(answer({}));
        await flushPromises();

        expect(modal().querySelector('.lookup-wait')).toBeNull();
        expect(modal().querySelector('.lookup-ro')?.textContent).toContain(
            'alertă',
        );
    });

    it('shows a course word: meaning, pronunciation, example, state, unit', async () => {
        await open('alert', 'I got an alert.');

        expect(modal().querySelector('.lookup-ipa')?.textContent).toContain(
            '/əˈlɜːt/ · noun',
        );
        expect(modal().textContent).toContain('The alert woke me up.');
        expect(modal().textContent).toContain('Alerta m-a trezit.');
        expect(modal().textContent).toContain('🆕 To learn');
        expect(modal().textContent).toContain('From: B1 · Unit 4');
        expect(modal().textContent).not.toContain('extra word');
        expect(buttons()).toEqual(['🧠 Learn it', '✓ I already know it']);
    });

    it('says which form of the headword was tapped', async () => {
        await open(
            'alerts',
            '',
            Promise.resolve(answer({ word: 'alerts', relation: 'plural' })),
        );

        expect(modal().querySelector('.lookup-from')?.textContent).toMatch(
            /alerts — plural of\s+alert/,
        );
        expect(lookupWord).toHaveBeenCalledWith('alerts', undefined);
    });

    it('gives the three forms of an irregular verb and the expressions of the sentence', async () => {
        await open(
            'went',
            'We went on call.',
            Promise.resolve(
                answer({
                    word: 'went',
                    base: 'go',
                    relation: 'past',
                    entry: {
                        en: 'go',
                        ro: 'a merge',
                        ipa: null,
                        pos: 'verb',
                        ex: null,
                        exRo: null,
                        emoji: null,
                        wiki: null,
                        levelId: 'a1',
                        unit: 'a1-u02',
                    },
                    verb: {
                        v1: 'go',
                        v2: 'went',
                        v3: 'gone',
                        ipa: '/ɡəʊ/',
                        ro: 'a merge',
                    },
                    phrases: [{ en: 'on call', ro: 'de gardă' }],
                    state: null,
                }),
            ),
        );

        expect(modal().querySelector('.lookup-verb')?.textContent).toContain(
            'go – went – gone',
        );
        expect(modal().querySelector('.lookup-ipa')?.textContent).toContain(
            '/ɡəʊ/',
        );
        expect(modal().querySelector('.lookup-phrase')?.textContent).toMatch(
            /on call\s+— de gardă/,
        );
        expect(modal().textContent).toContain('went — past of');
        expect(modal().textContent).toContain(
            'An irregular verb: its three forms have their own practice.',
        );
        expect(modal().querySelector('.lookup-state')).toBeNull();
    });

    it('treats a glossary word as an extra word with the same actions', async () => {
        await open(
            'the',
            '',
            Promise.resolve(
                answer({
                    word: 'the',
                    source: 'glossary',
                    base: 'the',
                    entry: {
                        en: 'the',
                        ro: 'articol hotărât',
                        ipa: '/ðə/, /ðiː/',
                        pos: 'article',
                        ex: null,
                        exRo: null,
                        emoji: null,
                        wiki: null,
                        levelId: null,
                        unit: null,
                    },
                }),
            ),
        );

        expect(modal().textContent).toContain('➕ extra word');
        expect(modal().textContent).not.toContain('From:');
        expect(modal().querySelectorAll('.example')).toHaveLength(0);
        expect(buttons()).toEqual(['🧠 Learn it', '✓ I already know it']);
    });

    it('shows a name with its note and nothing to learn', async () => {
        await open(
            'Iași',
            '',
            Promise.resolve(
                answer({
                    word: 'Iași',
                    source: 'name',
                    base: 'Iași',
                    entry: {
                        en: 'Iași',
                        ro: 'oraș din România',
                        ipa: null,
                        pos: null,
                        ex: null,
                        exRo: null,
                        emoji: null,
                        wiki: null,
                        levelId: null,
                        unit: null,
                    },
                    state: null,
                }),
            ),
        );

        expect(modal().querySelector('.lookup-note')?.textContent).toContain(
            'oraș din România',
        );
        expect(modal().querySelector('.lookup-state')).toBeNull();
    });

    it('says honestly when a word is not in the course vocabulary', async () => {
        await open(
            'zorp',
            'A zorp here.',
            Promise.resolve(
                answer({
                    word: 'zorp',
                    found: false,
                    source: null,
                    base: null,
                    entry: null,
                    state: null,
                }),
            ),
        );

        expect(modal().textContent).toContain('Not in the course vocabulary');
        expect(modal().querySelector('.lookup-state')).toBeNull();
        expect(modal().textContent).toContain('A zorp here.');
    });

    it('keeps hearing and saying when the lookup fails', async () => {
        await open('alert', '', new HttpError(500, 'Server error'));

        expect(modal().textContent).toContain(
            'The meaning could not be loaded.',
        );

        await press('🐢');
        expect(speak).toHaveBeenLastCalledWith('alert', { slow: true });

        await press('🎙 Say it');
        expect(wrapper?.findComponent(MicPanel).exists()).toBe(true);
    });

    it('learns the headword and shows its new state', async () => {
        vi.mocked(learnWord).mockReturnValue(
            saved('alert', { status: 'learning', box: 0 }),
        );
        await open('alerts');

        await press('🧠 Learn it');

        expect(learnWord).toHaveBeenCalledWith('alert');
        expect(modal().textContent).toContain('🌱 Learning');
        expect(buttons()).toEqual([
            '✓ I already know it',
            'Remove from flashcards',
        ]);
        expect(wrapper?.emitted('changed')).toHaveLength(1);
    });

    it('marks it as known, practises it again and takes it out of the flashcards', async () => {
        vi.mocked(markKnown).mockReturnValue(
            saved('alert', { status: 'learned', box: 6, known: true }),
        );
        vi.mocked(learnWord).mockReturnValue(
            saved('alert', { status: 'learning', box: 0 }),
        );
        vi.mocked(forgetWord).mockReturnValue(
            saved('alert', { status: 'new' }),
        );
        await open('alert');

        await press('✓ I already know it');
        expect(markKnown).toHaveBeenCalledWith('alert');
        expect(modal().textContent).toContain('marked as known by you');
        expect(buttons()).toEqual([
            '↺ Practise it again',
            'Remove from flashcards',
        ]);

        await press('↺ Practise it again');
        expect(learnWord).toHaveBeenCalledWith('alert');

        await press('Remove from flashcards');
        expect(forgetWord).toHaveBeenCalledWith('alert');
        expect(buttons()).toEqual(['🧠 Learn it', '✓ I already know it']);
        expect(wrapper?.emitted('changed')).toHaveLength(3);
    });

    it('stays as it was when a change is not saved', async () => {
        vi.mocked(markKnown).mockRejectedValue(
            new HttpError(500, 'Server error'),
        );
        await open(
            'alert',
            '',
            Promise.resolve(answer({ state: LEARNING as never })),
        );

        await press('✓ I already know it');

        expect(modal().textContent).toContain('🌱 Learning');
        expect(wrapper?.emitted('changed')).toBeUndefined();
    });

    it('checks the word said into the microphone', async () => {
        await open('started');
        await press('🎙 Say it');
        const mic = wrapper!.findComponent(MicPanel);

        mic.vm.$emit('heard', ['it started']);
        await flushPromises();
        expect(modal().querySelector('.feedback.good')?.textContent).toContain(
            'Yes — “started” ✅',
        );

        mic.vm.$emit('heard', ['stared']);
        await flushPromises();
        expect(modal().querySelector('.feedback.bad')?.textContent).toContain(
            'I did not catch the word',
        );
        expect(modal().textContent).toContain('„stared”');
    });

    it('closes on Esc', async () => {
        await open('alert');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(wrapper?.emitted('close')).toHaveLength(1);
    });
});
