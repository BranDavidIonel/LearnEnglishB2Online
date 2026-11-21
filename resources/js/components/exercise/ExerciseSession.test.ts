// @vitest-environment happy-dom
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { trans } from '@/i18n';
import type { Replacements } from '@/i18n';
import type { Exercise, ExerciseItem, ExerciseResult } from '@/types';
import { stopSpeak } from '@/utils/speech';
import ExerciseSession from './ExerciseSession.vue';
import ResultCard from './ResultCard.vue';

vi.mock('@/utils/speech', () => ({ speak: vi.fn(), stopSpeak: vi.fn() }));

const global = {
    mocks: {
        $t: (key: string, replacements?: Replacements) =>
            trans(key, replacements, 'en'),
    },
};

const mc: Exercise = {
    type: 'mc',
    q: 'She ___ a teacher.',
    options: ['am', 'is', 'are'],
    answer: 1,
    explainRo: 'Cu **she** folosim is.',
};
const fill: Exercise = {
    type: 'fill',
    q: 'They ___ (to be) from Spain.',
    answers: ['are', "'re"],
    explainRo: 'They + are.',
};
const order: Exercise = {
    type: 'order',
    words: ['Where', 'are', 'you', 'from?'],
    ro: 'De unde ești?',
};
const translate: Exercise = {
    type: 'translate',
    ro: 'Eu sunt student.',
    answers: ['I am a student.', "I'm a student."],
};
const match: Exercise = {
    type: 'match',
    pairs: [
        ['dog', 'câine'],
        ['cat', 'pisică'],
    ],
};

let wrapper: VueWrapper | null = null;

function start(
    items: ExerciseItem[],
    mode: 'practice' | 'test' = 'practice',
): VueWrapper {
    wrapper = mount(ExerciseSession, {
        props: { items, mode, title: 'Quick check' },
        global,
        attachTo: document.body,
    });

    return wrapper;
}

const press = (key: string): void => {
    document.dispatchEvent(new KeyboardEvent('keydown', { key }));
};

async function pickOption(session: VueWrapper, text: string): Promise<void> {
    const option = session
        .findAll<HTMLButtonElement>('button.opt')
        .find((b) => b.text().endsWith(text));
    await option?.trigger('click');
}

async function type(session: VueWrapper, value: string): Promise<void> {
    const field = session.find<HTMLInputElement>('input, textarea');
    await field.setValue(value);
}

async function next(session: VueWrapper): Promise<void> {
    await session.find('.ex-foot .btn.big').trigger('click');
}

function finished(session: VueWrapper): ExerciseResult[] {
    const events = session.emitted<[ExerciseResult[]]>('finish');

    expect(events).toHaveLength(1);

    return events?.[0][0] ?? [];
}

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('ExerciseSession', () => {
    it('shows the title, the counter and the type of the exercise', () => {
        const session = start([{ ex: mc }, { ex: fill }]);

        expect(session.find('.sess-title').text()).toBe('Quick check');
        expect(session.find('.sess-count').text()).toBe('1 / 2');
        expect(session.find('.ex-type').text()).toBe(
            'Choose the correct answer',
        );
        expect(session.find('.ex-q').html()).toContain(
            '<span class="gap"></span>',
        );
    });

    it('answers multiple choice with one click and explains it in practice', async () => {
        const session = start([{ ex: mc, unit: 'a1-u01', key: 'a1-u01:0' }]);
        await pickOption(session, 'are');

        expect(session.find('.feedback.bad .fb-head').text()).toBe(
            'Not quite… ❌',
        );
        expect(session.find('.fb-answer').text()).toBe('Correct answer: is');
        expect(session.find('.fb-explain').html()).toContain(
            '<strong>she</strong>',
        );
        expect(session.find('button.opt.right').text()).toContain('is');
        expect(session.find('button.opt.wrong').text()).toContain('are');
        expect(
            session
                .findAll<HTMLButtonElement>('button.opt')
                .every((b) => b.element.disabled),
        ).toBe(true);
        expect(session.find('.ex-foot .btn.big.danger').element).toBe(
            document.activeElement,
        );
    });

    it('picks an option with the number keys', async () => {
        const session = start([{ ex: mc }]);
        const second = session.findAll<HTMLButtonElement>('button.opt')[1];
        const isRight = second.text() === '2is';
        press('2');
        await flushPromises();

        expect(second.classes(isRight ? 'right' : 'wrong')).toBe(true);
        expect(session.find('.feedback').classes('good')).toBe(isRight);
    });

    it('repeats a wrong answer at the end of a practice and reports first attempts only', async () => {
        const session = start([
            { ex: mc, unit: 'a1-u01', key: 'k-mc' },
            { ex: fill, unit: 'a1-u01', key: 'k-fill' },
        ]);
        await pickOption(session, 'is');
        expect(session.find('.feedback.good').exists()).toBe(true);
        await next(session);

        expect(session.find('.sess-count').text()).toBe('2 / 2');
        expect(session.find('.bar-fill').attributes('style')).toContain(
            'width: 50%',
        );
        expect(
            session.find<HTMLButtonElement>('.ex-foot button').element.disabled,
        ).toBe(true);
        await type(session, 'is');
        press('Enter');
        await flushPromises();
        expect(session.find('.fb-answer').text()).toBe('Correct answer: are');
        expect(session.find('input.gap-input.wrong').exists()).toBe(true);
        press('Enter');
        await flushPromises();

        expect(session.find('.sess-count').text()).toBe('Retry');
        expect(session.find('.ex-type').text()).toBe(
            '🔁 One more time: Fill in the gap',
        );
        await type(session, "'re");
        await session.find('.ex-foot button').trigger('click');
        await next(session);

        expect(finished(session)).toEqual([
            { ex: mc, unit: 'a1-u01', key: 'k-mc', correct: true },
            { ex: fill, unit: 'a1-u01', key: 'k-fill', correct: false },
        ]);
        expect(session.find('.ex').exists()).toBe(false);
    });

    it('never repeats nor explains in a test', async () => {
        const session = start([{ ex: mc }, { ex: fill }], 'test');
        await pickOption(session, 'am');
        expect(session.find('.fb-explain').exists()).toBe(false);
        await next(session);
        await type(session, 'is');
        press('Enter');
        await flushPromises();
        await next(session);

        expect(finished(session).map((r) => r.correct)).toEqual([false, false]);
    });

    it('checks the word order and accepts the sentence tapped in order', async () => {
        const session = start([{ ex: order }]);
        const bank = (): string[] =>
            session
                .findAll<HTMLButtonElement>('.order-bank .chip')
                .map((b) => b.text());

        expect(bank()).not.toEqual(order.words);

        for (const word of order.words) {
            await session
                .findAll<HTMLButtonElement>('.order-bank .chip')
                .find((b) => b.text() === word && !b.element.disabled)
                ?.trigger('click');
        }

        expect(
            session.findAll('.order-line .chip').map((b) => b.text()),
        ).toEqual(order.words);
        await session.find('.ex-foot button').trigger('click');

        expect(session.find('.order-line.right').exists()).toBe(true);
        expect(session.find('.feedback.good').exists()).toBe(true);
    });

    it('forgives one typo in a translation and shows the right spelling', async () => {
        const session = start([{ ex: translate }]);
        await type(session, 'I am a studnt');
        await session.find('.ex-foot button').trigger('click');

        expect(session.find('.fb-head').text()).toBe(
            'Correct, but with a small spelling mistake ✍️',
        );
        expect(session.find('.fb-answer').text()).toBe(
            'Correct spelling: I am a student.',
        );
    });

    it('counts match pairs right with at most one wrong try', async () => {
        const session = start([{ ex: match }, { ex: match }]);
        const tap = (text: string) =>
            session
                .findAll('.match-item')
                .find((b) => b.text() === text)
                ?.trigger('click');

        await tap('dog');
        await tap('pisică');
        expect(session.findAll('.match-item.shake')).toHaveLength(2);
        await tap('dog');
        await tap('câine');
        await tap('cat');
        await tap('pisică');
        expect(session.find('.feedback.good').exists()).toBe(true);
        await next(session);

        await tap('câine');
        await tap('cat');
        await tap('pisică');
        await tap('dog');
        await tap('dog');
        await tap('câine');
        await tap('cat');
        await tap('pisică');
        expect(session.find('.feedback.bad').exists()).toBe(true);
    });

    it('leaves only after the learner confirms', async () => {
        // happy-dom has no dialogs: answer the question ourselves.
        const confirm = vi.fn(() => false);
        vi.stubGlobal('confirm', confirm);
        const session = start([{ ex: mc }]);
        await session.find('.sess-top .icon-btn').trigger('click');
        expect(session.emitted('exit')).toBeUndefined();

        confirm.mockReturnValue(true);
        await session.find('.sess-top .icon-btn').trigger('click');
        expect(session.emitted('exit')).toHaveLength(1);
    });

    it('releases the keyboard and stops speech when it goes away', () => {
        const remove = vi.spyOn(document, 'removeEventListener');
        start([{ ex: mc }]).unmount();
        wrapper = null;

        expect(remove).toHaveBeenCalledWith('keydown', expect.any(Function));
        expect(stopSpeak).toHaveBeenCalled();
    });

    it('finishes at once without questions', () => {
        expect(finished(start([]))).toEqual([]);
    });
});

describe('ResultCard', () => {
    it('shows the score, the pass mark, the lines and the actions', () => {
        const card = mount(ResultCard, {
            props: {
                score: 0.666,
                title: 'Not yet',
                passed: false,
                passMark: 0.8,
                lines: ['**4** of 6', ''],
            },
            slots: { actions: '<button class="btn">Again</button>' },
            global,
        });

        expect(card.find('.result-ring.fail').attributes('style')).toContain(
            '--p: 67',
        );
        expect(card.find('.result-ring span').text()).toBe('67%');
        expect(card.find('p.muted').text()).toBe('Pass mark: 80%');
        expect(card.findAll('p')[1].html()).toContain('<strong>4</strong>');
        expect(card.find('.row.center.wrap .btn').text()).toBe('Again');
    });

    it('keeps the ring green when there is no pass mark', () => {
        const card = mount(ResultCard, {
            props: { score: 1, title: 'Done' },
            global,
        });

        expect(card.find('.result-ring').classes()).not.toContain('fail');
        expect(card.find('p.muted').exists()).toBe(false);
    });
});
