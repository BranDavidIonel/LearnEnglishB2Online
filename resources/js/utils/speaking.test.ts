import { describe, expect, it } from 'vite-plus/test';
import type { SpeakingScenario } from '@/types';
import {
    allSaid,
    averageScore,
    dialogueSpeakers,
    hasPhrase,
    judgeTurn,
    letterHint,
    pickLevel,
    roleConversation,
    saidWord,
    scenarioConversation,
    sentencePassMark,
    SOUND_ALIKE_PAIRS,
    wordCount,
} from './speaking';

const scenario: SpeakingScenario = {
    id: 'a1-s01',
    emoji: '☕',
    title: 'At the café',
    titleRo: 'La cafenea',
    youRo: 'Comanzi o cafea.',
    partner: 'Waiter',
    turns: [
        {
            say: 'Hi! What can I get you?',
            sayRo: 'Bună! Ce vă aduc?',
            hintRo: 'Cere o cafea.',
            answers: ["I'd like a coffee, please.", 'A coffee, please.'],
            keywords: ['coffee'],
        },
    ],
    closing: 'Here you are!',
};

const dialogue = {
    titleRo: 'La birou',
    lines: [
        { speaker: 'Laura', en: 'Hi, I am Laura.', ro: 'Bună, sunt Laura.' },
        { speaker: 'Andrei', en: 'Nice to meet you.', ro: 'Încântat.' },
        { speaker: 'Laura', en: 'Welcome!', ro: 'Bine ai venit!' },
    ],
};

describe('pickLevel', () => {
    it('keeps the level chosen on this device while it is open', () => {
        expect(pickLevel('a2', ['a1', 'a2', 'b1'], 'b1')).toBe('a2');
    });

    it('falls back when nothing was chosen or the level is locked', () => {
        expect(pickLevel(null, ['a1'], 'a1')).toBe('a1');
        expect(pickLevel('b2', ['a1', 'a2'], 'a2')).toBe('a2');
        expect(pickLevel('zz', ['a1'], 'a1')).toBe('a1');
    });
});

describe('saidWord', () => {
    it('finds a word said among others', () => {
        expect(saidWord('I said sheep', 'sheep')).toBe(true);
        expect(saidWord('I said ship', 'sheep')).toBe(false);
    });

    it('does not take part of a word for the word', () => {
        expect(saidWord('sheepish', 'sheep')).toBe(false);
    });

    it('ignores hyphens, spacing and case', () => {
        expect(saidWord('He is Easy Going', 'easy-going')).toBe(true);
        expect(saidWord('easygoing', 'easy-going')).toBe(true);
        expect(saidWord('easygoing', 'easy going')).toBe(true);
    });

    it('is false for an empty text or target', () => {
        expect(saidWord('', 'ship')).toBe(false);
        expect(saidWord('ship', '')).toBe(false);
    });
});

describe('hasPhrase', () => {
    it('matches whole words after normalising', () => {
        expect(hasPhrase("I've BOOKED a room.", 'booked')).toBe(true);
        expect(hasPhrase('I am rebooking', 'booked')).toBe(false);
        expect(hasPhrase("I'm here", 'I am')).toBe(true);
    });
});

describe('letterHint', () => {
    it('shows the first letter of each word and blanks for the rest', () => {
        expect(letterHint('easy-going')).toBe('e _ _ _ - g _ _ _ _');
        expect(letterHint('ice cream')).toBe('i _ _    c _ _ _ _');
        expect(letterHint('go')).toBe('g _');
    });
});

describe('wordCount', () => {
    it('counts the words of a spoken text', () => {
        expect(wordCount('  I work as a developer ')).toBe(5);
        expect(wordCount('   ')).toBe(0);
    });
});

describe('scenarioConversation', () => {
    it('alternates the partner and the learner and ends with the closing line', () => {
        const conv = scenarioConversation(scenario, 'You');

        expect(conv).toMatchObject({
            id: 'a1-s01',
            title: '☕ At the café',
            partner: 'Waiter',
            me: 'You',
            intro: 'Comanzi o cafea.',
        });
        expect(conv.turns).toEqual([
            {
                who: 'partner',
                en: 'Hi! What can I get you?',
                ro: 'Bună! Ce vă aduc?',
            },
            {
                who: 'me',
                hint: 'Cere o cafea.',
                answers: ["I'd like a coffee, please.", 'A coffee, please.'],
                keywords: ['coffee'],
            },
            { who: 'partner', en: 'Here you are!' },
        ]);
    });

    it('has no closing turn when the scenario has none', () => {
        const conv = scenarioConversation(
            { ...scenario, closing: undefined },
            'You',
        );

        expect(conv.turns).toHaveLength(2);
    });
});

describe('roleConversation', () => {
    it('gives the learner the lines of the chosen character', () => {
        const conv = roleConversation(
            'a1-u01',
            dialogue,
            'Andrei',
            false,
            'Read it.',
        );

        expect(conv.id).toBe('role-a1-u01');
        expect(conv.title).toBe('🎭 La birou');
        expect(conv.partner).toBe('Laura');
        expect(conv.turns.map((t) => t.who)).toEqual([
            'partner',
            'me',
            'partner',
        ]);
        expect(conv.turns[1]).toEqual({
            who: 'me',
            hint: 'Încântat.',
            answers: ['Nice to meet you.'],
            keywords: [],
            show: true,
        });
    });

    it('hides the English line in hard mode', () => {
        const conv = roleConversation('a1-u01', dialogue, 'Laura', true, '');

        expect(conv.turns[0]).toMatchObject({ who: 'me', show: false });
        expect(conv.partner).toBe('Andrei');
    });

    it('lists the characters in the order they first speak', () => {
        expect(dialogueSpeakers(dialogue.lines)).toEqual(['Laura', 'Andrei']);
    });
});

describe('judgeTurn', () => {
    const turn = {
        who: 'me' as const,
        hint: '',
        answers: [
            'I have booked a room for this week',
            'I booked a room online',
        ],
        keywords: [],
    };

    it('passes from 60% of the closest answer when the line is not shown', () => {
        const verdict = judgeTurn(turn, ['I booked a room']);

        expect(verdict.pass).toBe(true);
        expect(verdict.answer).toBe('I booked a room online');
        expect(verdict.heard).toBe('I booked a room');
    });

    it('needs 70% when the line is shown', () => {
        const shown = {
            ...turn,
            answers: ['one two three four five six seven eight nine ten'],
            show: true,
        };

        expect(judgeTurn(shown, ['one two three four five six']).pass).toBe(
            false,
        );
        expect(
            judgeTurn(shown, ['one two three four five six seven']).pass,
        ).toBe(true);
    });

    it('passes when every keyword was said in one alternative', () => {
        const withKeywords = { ...turn, keywords: ['booked'] };
        const verdict = judgeTurn(withKeywords, [
            'hello there',
            'yes I booked it',
        ]);

        expect(verdict.pass).toBe(true);
        expect(verdict.heard).toBe('yes I booked it');
    });

    it('fails when nothing matches', () => {
        const verdict = judgeTurn({ ...turn, keywords: ['booked'] }, [
            'good morning',
        ]);

        expect(verdict.pass).toBe(false);
        expect(verdict.words.every((w) => !w.hit)).toBe(true);
    });
});

describe('scores and pass marks', () => {
    it('averages the lines of a conversation', () => {
        expect(averageScore([1, 0.7, 0])).toBeCloseTo(0.5667, 3);
        expect(averageScore([])).toBe(0);
    });

    it('marks every word as said for a learner who checks themself', () => {
        expect(allSaid('Nice to meet you.')).toEqual([
            { w: 'Nice', hit: true },
            { w: 'to', hit: true },
            { w: 'meet', hit: true },
            { w: 'you.', hit: true },
        ]);
    });

    it('asks more of a repeated sentence than of one said from Romanian', () => {
        expect(sentencePassMark(false)).toBe(0.8);
        expect(sentencePassMark(true)).toBe(0.7);
    });
});

describe('SOUND_ALIKE_PAIRS', () => {
    it('keeps the 30 pairs of two different words', () => {
        expect(SOUND_ALIKE_PAIRS).toHaveLength(30);
        expect(SOUND_ALIKE_PAIRS.every(([a, b]) => a !== b)).toBe(true);
    });
});
