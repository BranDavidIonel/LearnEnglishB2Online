// @vitest-environment happy-dom
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import type { Component } from 'vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    addXp,
    countSpoken,
    recordConversation,
    speakRecord,
} from '@/api/progress';
import { trans } from '@/i18n';
import type { Replacements } from '@/i18n';
import { canListen, listen, listenStream, micError } from '@/utils/recognition';
import type { Conversation } from '@/utils/speaking';
import ConversationRun from './ConversationRun.vue';
import MicPanel from './MicPanel.vue';
import PairsRound from './PairsRound.vue';
import SentenceRound from './SentenceRound.vue';
import VoiceSprint from './VoiceSprint.vue';

vi.mock('@/utils/speech', () => ({ speak: vi.fn(), stopSpeak: vi.fn() }));
vi.mock('@/utils/sound', () => ({ sfx: vi.fn() }));
vi.mock('@/utils/confetti', () => ({ confetti: vi.fn() }));
vi.mock('@/utils/recording', () => ({ canRecord: () => true }));
vi.mock('@/utils/recognition', async (original) => ({
    ...(await original<typeof import('@/utils/recognition')>()),
    canListen: vi.fn(() => false),
    listen: vi.fn(),
    listenStream: vi.fn(),
    speechEngine: () => 'cloud',
}));
vi.mock('@/api/progress', () => ({
    addXp: vi.fn(async () => ({ xp: 0 })),
    countSpoken: vi.fn(async (count = 1, xp = 2) => ({
        xp: count * xp,
        said: 1,
    })),
    recordConversation: vi.fn(async () => ({ first: true, xp: 15, best: 1 })),
    speakRecord: vi.fn(async () => ({ record: true, best: {} })),
}));

const global = {
    mocks: {
        $t: (key: string, replacements?: Replacements) =>
            trans(key, replacements, 'en'),
    },
    stubs: { Link: true, Recorder: { template: '<div class="recorder" />' } },
};

let wrapper: VueWrapper | null = null;

function mountWith(
    component: Component,
    props: Record<string, unknown>,
): VueWrapper {
    wrapper = mount(component, {
        props,
        global,
        attachTo: document.body,
    });

    return wrapper;
}

/** Says something into the microphone of the current exercise (null: "I said it well"). */
async function hear(heard: string[] | null): Promise<void> {
    wrapper?.findComponent(MicPanel).vm.$emit('heard', heard);
    await flushPromises();
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.mocked(canListen).mockReturnValue(false);
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
    vi.useRealTimers();
    vi.clearAllMocks();
});

describe('MicPanel', () => {
    it('lets the learner check themself without speech recognition', async () => {
        const panel = mountWith(MicPanel, {});

        expect(panel.text()).toContain(
            'This browser has no speech recognition',
        );
        expect(panel.find('.recorder').exists()).toBe(true);

        await panel.find('button.btn.success').trigger('click');

        expect(panel.emitted('heard')).toEqual([[null]]);
    });

    it('hands over what was heard', async () => {
        vi.mocked(canListen).mockReturnValue(true);
        vi.mocked(listen).mockReturnValue({
            done: Promise.resolve(['nice to meet you']),
            stop: vi.fn(),
        });
        const panel = mountWith(MicPanel, {});

        await panel.find('button.mic-big').trigger('click');
        await flushPromises();

        expect(panel.emitted('heard')).toEqual([[['nice to meet you']]]);
        expect(panel.find('.mic-status').text()).toBe('');
    });

    it('explains an error and offers the recorder after a fatal one', async () => {
        vi.mocked(canListen).mockReturnValue(true);
        vi.mocked(listen).mockReturnValue({
            done: Promise.reject(micError('network')),
            stop: vi.fn(),
        });
        const panel = mountWith(MicPanel, {});

        await panel.find('button.mic-big').trigger('click');
        await flushPromises();

        expect(panel.find('.mic-status.bad').text()).toContain(
            '(code: network)',
        );
        expect(panel.find('.recorder').exists()).toBe(true);
        expect(panel.emitted('heard')).toBeUndefined();
    });

    it('stops listening when it goes away', async () => {
        vi.mocked(canListen).mockReturnValue(true);
        const stop = vi.fn();
        vi.mocked(listen).mockReturnValue({
            done: new Promise(() => {}),
            stop,
        });
        const panel = mountWith(MicPanel, {});

        await panel.find('button.mic-big').trigger('click');
        panel.unmount();
        wrapper = null;

        expect(stop).toHaveBeenCalled();
    });
});

describe('ConversationRun', () => {
    const conversation: Conversation = {
        id: 'a1-s01',
        title: '🏨 At the hotel',
        partner: 'Receptionist',
        me: 'You',
        intro: 'Ajungi la hotel.',
        turns: [
            { who: 'partner', en: 'Good evening!', ro: 'Bună seara!' },
            {
                who: 'me',
                hint: 'Spune că ai rezervat o cameră.',
                answers: ['I have booked a room.', 'I booked a room online.'],
                keywords: ['booked'],
            },
            { who: 'partner', en: 'Here is your key.' },
        ],
    };

    it('plays the partner, judges the learner and saves the score', async () => {
        const run = mountWith(ConversationRun, {
            conversation,
            back: '/speaking/talk',
        });
        await flushPromises();

        expect(run.find('.card.instr').text()).toBe('Ajungi la hotel.');
        expect(run.find('.line.left .en').text()).toBe('Good evening!');
        expect(run.find('.turn-head').text()).toContain('Your turn');

        await hear(['good morning']);
        expect(run.find('.feedback.bad').text()).toContain('„good morning”');
        expect(countSpoken).not.toHaveBeenCalled();

        await hear(['good morning', 'yes I booked it']);
        expect(countSpoken).toHaveBeenCalledOnce();
        expect(run.find('.line.right .en').text()).toBe('yes I booked it');
        expect(run.find('.line.right .muted').text()).toBe(
            '✓ Model: I have booked a room.',
        );

        await vi.advanceTimersByTimeAsync(700);
        await flushPromises();

        // A second try is worth 70%.
        expect(recordConversation).toHaveBeenCalledWith('a1-s01', 0.7);
        expect(run.findAll('.line.left')).toHaveLength(2);
        expect(run.find('.result').text()).toContain(
            '1 of 1 lines said · +15 XP ⚡',
        );
        expect(run.find('.result h2').text()).toBe(
            'Good! The conversation worked.',
        );
    });

    it('shows the model answer after two misses and counts a skipped line as 0', async () => {
        const run = mountWith(ConversationRun, {
            conversation,
            back: '/speaking/talk',
        });
        await flushPromises();

        await hear(['hello']);
        expect(run.find('.example').exists()).toBe(false);
        await hear(['hello again']);
        expect(run.find('.example .ro').text()).toBe(
            'or: I booked a room online.',
        );

        const skip = run.findAll('button').find((b) => b.text() === 'Skip →');
        await skip?.trigger('click');
        await vi.advanceTimersByTimeAsync(700);
        await flushPromises();

        expect(recordConversation).toHaveBeenCalledWith('a1-s01', 0);
        expect(run.find('.line.right .muted').text()).toBe('skipped');
        expect(run.find('.result').text()).toContain('0 of 1 lines said');
    });

    it('says when the result was not saved and sends it again', async () => {
        // Offline while the line is said, and still when the round ends.
        vi.mocked(countSpoken)
            .mockRejectedValueOnce(new Error('offline'))
            .mockRejectedValueOnce(new Error('offline'));
        const run = mountWith(ConversationRun, {
            conversation,
            back: '/speaking/talk',
        });
        await flushPromises();

        await hear(['I have booked a room']);
        await vi.advanceTimersByTimeAsync(700);
        await flushPromises();

        // The failed sentence holds back the result: nothing is shown as saved.
        expect(run.find('.result').exists()).toBe(false);
        expect(run.find('.alert.warn').text()).toContain(
            'Your result could not be saved',
        );
        expect(recordConversation).not.toHaveBeenCalled();

        await run.find('.alert.warn button').trigger('click');
        await flushPromises();

        expect(countSpoken).toHaveBeenCalledTimes(3);
        expect(recordConversation).toHaveBeenCalledOnce();
        expect(run.find('.alert.warn').exists()).toBe(false);
        expect(run.find('.result').text()).toContain(
            '1 of 1 lines said · +15 XP ⚡',
        );
    });
});

describe('SentenceRound', () => {
    const items = [
        { en: 'Nice to meet you.', ro: 'Încântat.' },
        { en: 'See you tomorrow.', ro: 'Ne vedem mâine.' },
    ];

    it('hides the sentence until it is said, and counts the good ones', async () => {
        const round = mountWith(SentenceRound, {
            items,
            title: 'Say it in English',
            hidden: true,
            back: '/speaking',
        });
        await flushPromises();

        expect(round.find('.ex-q').text()).toBe('🇷🇴 Încântat.');
        expect(round.find('.say-target').text()).toBe('•••');

        await hear(['nice to see']);
        expect(round.find('.feedback.bad .fb-head').text()).toBe(
            '50% — try again',
        );
        expect(round.find('.say-target').text()).toBe('•••');

        await hear(['nice to meet you']);
        expect(round.find('.feedback.good').text()).toBe('Perfect! ⭐');
        expect(round.findAll('.tw.hit')).toHaveLength(4);
        expect(countSpoken).toHaveBeenCalledOnce();

        await vi.advanceTimersByTimeAsync(1900);
        expect(round.find('.sess-count').text()).toBe('2 / 2');

        await hear(['see']);
        await hear(['see you']);
        // Two misses show the sentence with what was missed.
        expect(round.findAll('.tw.miss')).toHaveLength(1);

        const skip = round.findAll('button').find((b) => b.text() === 'Skip →');
        await skip?.trigger('click');
        await flushPromises();

        expect(round.find('.result').text()).toContain(
            '1 of 2 said well · +2 XP ⚡',
        );
        expect(round.find('.result h2').text()).toBe('Round finished');
    });
});

describe('PairsRound', () => {
    it('counts the words said at the first try and keeps the record', async () => {
        const round = mountWith(PairsRound, { back: '/speaking' });
        await flushPromises();

        const target = round.find('.ex-q .tw.hit').text();
        const other = round
            .findAll('.pair-card strong')
            .map((w) => w.text())
            .find((w) => w !== target);

        await hear([`I said ${other}`]);
        expect(round.find('.feedback.bad .fb-head').text()).toBe(
            `I heard “${other}” — the other word`,
        );

        await hear([target]);
        await vi.advanceTimersByTimeAsync(1000);

        const second = round.find('.ex-q .tw.hit').text();
        await hear([second]);
        await vi.advanceTimersByTimeAsync(1000);

        for (let i = 2; i < 8; i++) {
            const skip = round
                .findAll('button')
                .find((b) => b.text() === 'Skip →');
            await skip?.trigger('click');
        }

        await flushPromises();

        expect(speakRecord).toHaveBeenCalledWith('pairs', 1);
        expect(addXp).toHaveBeenCalledWith(2);
        expect(round.find('.result h2').text()).toBe('New record! 🏆');
        expect(round.find('.result').text()).toContain('1 of 8 · +2 XP ⚡');
    });
});

describe('VoiceSprint', () => {
    const words = [{ en: 'easy-going', ro: 'relaxat', emoji: '😎' }];

    interface CapturedStream {
        text: (t: string) => void;
        stop: ReturnType<typeof vi.fn>;
        update: (...u: unknown[]) => void;
    }

    function captureStream(): CapturedStream {
        const stop = vi.fn();
        const captured: CapturedStream = {
            text: () => {},
            stop,
            update: () => {},
        };
        vi.mocked(listenStream).mockImplementation((onText, onstate) => {
            captured.text = onText;
            captured.update = onstate as (...u: unknown[]) => void;

            return { stop, reset: vi.fn() };
        });

        return captured;
    }

    it('scores the words said in 60 seconds and keeps the record', async () => {
        const stream = captureStream();
        const sprint = mountWith(VoiceSprint, { words, back: '/speaking' });

        expect(sprint.text()).toContain('Recognition engine: online.');
        await sprint.find('button.btn.primary').trigger('click');

        expect(sprint.find('.sprint-word').text()).toBe('😎 relaxat');
        expect(sprint.find('.sprint-hint').text()).toBe('e _ _ _ - g _ _ _ _');

        stream.text('he is easy going');
        await flushPromises();
        expect(sprint.find('.sprint-score').text()).toBe('1 word');

        await vi.advanceTimersByTimeAsync(60_000);
        await flushPromises();

        expect(stream.stop).toHaveBeenCalled();
        expect(speakRecord).toHaveBeenCalledWith('sprint', 1);
        expect(addXp).toHaveBeenCalledWith(2);
        expect(sprint.find('.big-level').text()).toBe('1');
        expect(sprint.find('.result h2').text()).toBe('New record! 🏆');
    });

    it('stops when the microphone fails, without a record', async () => {
        const stream = captureStream();
        const sprint = mountWith(VoiceSprint, { words, back: '/speaking' });

        await sprint.find('button.btn.primary').trigger('click');
        stream.update('error', micError('not-allowed'));
        await flushPromises();

        expect(speakRecord).not.toHaveBeenCalled();
        expect(sprint.find('.result h2').text()).toBe('The microphone stopped');
        expect(sprint.find('.result p').text()).toContain(
            '(code: not-allowed)',
        );
    });
});
