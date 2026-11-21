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
import { recordCambridge } from '@/api/progress';
import { trans } from '@/i18n';
import type { Replacements } from '@/i18n';
import type { CambridgePart } from '@/types';
import { speakScript, stopSpeak } from '@/utils/speech';
import Paper from './Paper.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { locale: 'en' } }),
}));
vi.mock('@/utils/speech', () => ({
    speakScript: vi.fn(),
    stopSpeak: vi.fn(),
}));
vi.mock('@/utils/sound', () => ({ sfx: vi.fn() }));
vi.mock('@/utils/confetti', () => ({ confetti: vi.fn() }));
vi.mock('@/utils/toast', () => ({ toast: vi.fn() }));
vi.mock('@/api/progress', () => ({ recordCambridge: vi.fn() }));

const global = {
    mocks: {
        $t: (key: string, replacements?: Replacements) =>
            trans(key, replacements, 'en'),
    },
};

const parts: CambridgePart[] = [
    {
        title: 'Part 1',
        instructionsRo: 'Alege varianta corectă.',
        audio: [{ s: 'Man', en: 'I take the bus to work.' }],
        questions: [
            {
                type: 'mc',
                q: '1 How does the man get to work?',
                options: ['by car', 'by bus', 'on foot'],
                answer: 1,
                explainRo: 'Spune **bus**.',
            },
        ],
    },
    {
        title: 'Part 2',
        instructionsRo: 'Completează.',
        questions: [
            {
                type: 'fill',
                q: '**(2)** The office opens at ___ .',
                answers: ['nine', '9'],
                explainRo: 'Ora nouă.',
            },
        ],
    },
];

let wrapper: VueWrapper | null = null;

function open(): VueWrapper {
    wrapper = mount(Paper, {
        props: {
            exam: { id: 'pet', name: 'B1 Preliminary (PET)', level: 'B1' },
            test: { id: 'pet-test1', title: 'Simulare 1' },
            paper: 'listening',
            data: { minutes: 30, parts },
            target: 0.7,
            close: 0.6,
        },
        global,
    });

    return wrapper;
}

async function answerAll(page: VueWrapper): Promise<void> {
    await page.findAll('.cq-opt')[1].trigger('click');
    await page.find('.btn.primary').trigger('click');
    await page.find('.gap-input').setValue(' 9 ');
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.mocked(recordCambridge).mockReset();
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    vi.useRealTimers();
});

describe('Cambridge paper', () => {
    it('numbers the questions over the whole paper and counts the answers per part', async () => {
        const page = open();

        expect(page.find('h1').text()).toBe('🎧 Listening · Mock test 1');
        expect(page.find('.cq-n').text()).toBe('1');
        expect(page.find('.cq-q').text()).toBe('How does the man get to work?');

        await page.findAll('.cq-opt')[1].trigger('click');

        expect(page.findAll('.tab small').map((t) => t.text())).toEqual([
            '1/1',
            '0/1',
        ]);

        await page.find('.btn.primary').trigger('click');

        expect(page.find('.cq-n').text()).toBe('2');
    });

    it('counts the plays of a recording and stops the countdown when it is left', async () => {
        const page = open();
        const audio = page.find('.btn.audio');

        await audio.trigger('click');
        expect(speakScript).toHaveBeenCalledWith(
            parts[0].audio,
            expect.any(Function),
        );
        expect(audio.text()).toBe('⏹ Stop');

        await audio.trigger('click');
        expect(stopSpeak).toHaveBeenCalled();
        expect(audio.text()).toBe('▶ Listen to the recording (plays: 1/2)');

        await audio.trigger('click');
        await audio.trigger('click');
        expect(audio.classes()).toContain('muted-btn');

        vi.advanceTimersByTime(3000);
        await page.vm.$nextTick();
        expect(page.find('.timer').text()).toBe('29:57');
        page.unmount();
        wrapper = null;
        expect(vi.getTimerCount()).toBe(0);
    });

    it('asks before handing in a paper with questions left', async () => {
        const confirm = vi
            .fn<(message?: string) => boolean>()
            .mockReturnValue(false);
        window.confirm = confirm;
        const page = open();

        await page.find('.btn.primary').trigger('click');
        await page.find('.btn.success').trigger('click');

        expect(confirm).toHaveBeenCalledWith(
            'You answered 0 of 2 questions. Hand in anyway?',
        );
        expect(recordCambridge).not.toHaveBeenCalled();
    });

    it('marks the paper, saves the result and shows the review', async () => {
        vi.mocked(recordCambridge).mockResolvedValue(
            {} as Awaited<ReturnType<typeof recordCambridge>>,
        );
        const page = open();

        await answerAll(page);
        await page.find('.btn.success').trigger('click');
        await flushPromises();

        expect(recordCambridge).toHaveBeenCalledWith(
            'pet',
            'pet-test1',
            'listening',
            {
                score: 1,
                correct: 2,
                total: 2,
                parts: [1, 1],
            },
        );
        expect(page.find('.timer').exists()).toBe(false);
        expect(page.find('.card.ready.ok h2').text()).toBe('2 of 2 correct');
        expect(page.find('.cq.right').exists()).toBe(true);
        expect(page.find('details.script').exists()).toBe(true);
        expect(page.find('.alert.warn').exists()).toBe(false);
    });

    it('keeps the review and offers to send the result again when saving fails', async () => {
        vi.mocked(recordCambridge)
            .mockRejectedValueOnce(new TypeError('Failed to fetch'))
            .mockResolvedValueOnce(
                {} as Awaited<ReturnType<typeof recordCambridge>>,
            );
        window.confirm = () => true;
        const page = open();

        await page.findAll('.cq-opt')[0].trigger('click');
        await page.find('.btn.primary').trigger('click');
        await page.find('.btn.success').trigger('click');
        await flushPromises();

        expect(page.find('.card.ready.bad h2').text()).toBe('0 of 2 correct');
        expect(page.find('.alert.warn').text()).toContain(
            'Your result could not be saved.',
        );

        await page.find('.alert.warn .btn').trigger('click');
        await flushPromises();

        expect(recordCambridge).toHaveBeenCalledTimes(2);
        expect(page.find('.alert.warn').exists()).toBe(false);

        await page.find('.btn.primary').trigger('click');
        expect(page.find('.gap-input').classes()).toContain('wrong');
        expect(page.find('.fb-answer').text()).toBe('Correct: nine / 9');
    });
});
