import { describe, expect, it } from 'vite-plus/test';
import {
    checkText,
    esc,
    gap,
    lev,
    md,
    norm,
    speechDetail,
    speechScore,
} from './text';

describe('esc', () => {
    it('escapes the five HTML characters', () => {
        expect(esc(`<a href="x">Tom & Jerry's</a>`)).toBe(
            '&lt;a href=&quot;x&quot;&gt;Tom &amp; Jerry&#39;s&lt;/a&gt;',
        );
    });

    it('turns any value into text', () => {
        expect(esc(42)).toBe('42');
    });
});

describe('md', () => {
    it('writes bold, italic and line breaks', () => {
        expect(md('**be** is *irregular*.\nYes')).toBe(
            '<strong>be</strong> is <em>irregular</em>.<br>Yes',
        );
    });

    it('escapes HTML before adding its own tags', () => {
        expect(md('<script>alert(1)</script> **ok**')).toBe(
            '&lt;script&gt;alert(1)&lt;/script&gt; <strong>ok</strong>',
        );
    });

    it('keeps a lone star and stars inside words as they are', () => {
        expect(md('5 * 3 and 2*3*4')).toBe('5 * 3 and 2*3*4');
    });

    it('needs a space, a bracket or the start before an italic word', () => {
        expect(md('(*really*)')).toBe('(<em>really</em>)');
        expect(md('*first* word')).toBe('<em>first</em> word');
    });

    it('returns an empty string for empty values', () => {
        expect(md('')).toBe('');
        expect(md(null)).toBe('');
        expect(md(undefined)).toBe('');
    });
});

describe('gap', () => {
    it('turns three or more underscores into a blank', () => {
        expect(gap('She ___ a teacher.')).toBe(
            'She <span class="gap"></span> a teacher.',
        );
        expect(gap('I _____ (be) here.')).toBe(
            'I <span class="gap"></span> (be) here.',
        );
    });

    it('leaves one or two underscores alone', () => {
        expect(gap('snake_case and a__b')).toBe('snake_case and a__b');
    });

    it('applies the markdown too', () => {
        expect(gap('**(27)** ___')).toBe(
            '<strong>(27)</strong> <span class="gap"></span>',
        );
    });
});

describe('norm', () => {
    it('lower-cases and removes punctuation', () => {
        expect(norm('Hello, World! (Really?) Yes; no: ok…')).toBe(
            'hello world really yes no ok',
        );
    });

    it('makes typographic quotes plain and drops double quotes', () => {
        expect(norm('I’m “fine”')).toBe('i am fine');
        expect(norm('don`t')).toBe('do not');
        expect(norm('It´s')).toBe('it is');
    });

    it('removes dashes between words but keeps hyphenated words', () => {
        expect(norm('well-known — really – yes - no')).toBe(
            'well-known really yes no',
        );
    });

    it('collapses spaces', () => {
        expect(norm('  a   lot \n of  ')).toBe('a lot of');
    });

    it('expands contractions', () => {
        expect(norm("I can't")).toBe('i can not');
        expect(norm('I cannot')).toBe('i can not');
        expect(norm("I won't")).toBe('i will not');
        expect(norm("we shan't")).toBe('we shall not');
        expect(norm("she doesn't")).toBe('she does not');
        expect(norm("they're")).toBe('they are');
        expect(norm("we've")).toBe('we have');
        expect(norm("you'll")).toBe('you will');
        expect(norm("what's that? there's one")).toBe(
            'what is that there is one',
        );
        expect(norm("let's go")).toBe('let us go');
    });

    it("keeps the possessive 's", () => {
        expect(norm("Tom's car")).toBe("tom's car");
    });

    it('can keep contractions as written', () => {
        expect(norm("I'm here.", false)).toBe("i'm here");
    });
});

describe('lev', () => {
    it('counts single-letter edits', () => {
        expect(lev('kitten', 'sitting')).toBe(3);
        expect(lev('same', 'same')).toBe(0);
        expect(lev('', 'abc')).toBe(3);
        expect(lev('abc', '')).toBe(3);
        expect(lev('flaw', 'lawn')).toBe(2);
    });
});

describe('checkText', () => {
    it('accepts any of the answers, ignoring case and punctuation', () => {
        expect(checkText('i am a student', ['I am a student.'])).toEqual({
            ok: true,
        });
        expect(checkText('  I AM a student!! ', ['I am a student.'])).toEqual({
            ok: true,
        });
    });

    it('treats a contraction and its full form as the same answer', () => {
        expect(checkText("I'm a student", ['I am a student.'])).toEqual({
            ok: true,
        });
        expect(checkText('They are', ["They're"])).toEqual({ ok: true });
        expect(checkText('isn’t', ['is not'])).toEqual({ ok: true });
    });

    it('accepts a short contracted answer of a gap', () => {
        expect(checkText("'re", ['are', "'re"])).toEqual({ ok: true });
    });

    it('rejects an empty or blank input', () => {
        expect(checkText('', ['']).ok).toBe(false);
        expect(checkText('   ', ['are']).ok).toBe(false);
        expect(checkText('...', ['are']).ok).toBe(false);
    });

    it('rejects a wrong answer', () => {
        expect(checkText('is', ['are'])).toEqual({ ok: false });
    });

    it('never tolerates typos unless asked', () => {
        expect(checkText('I am a studnt', ['I am a student'])).toEqual({
            ok: false,
        });
    });

    it('tolerates one typo in answers of ten characters or more', () => {
        expect(checkText('I am a studnt', ['I am a student'], true)).toEqual({
            ok: true,
            typo: true,
        });
        // "breakfast" has 9 characters: too short for any tolerance
        expect(checkText('brekfast', ['breakfast'], true)).toEqual({
            ok: false,
        });
        // exactly 10 characters after normalising
        expect(checkText('beautifil', ['beautiful!'], true).ok).toBe(false);
        expect(checkText('aaaaabbbbx', ['aaaaabbbbb'], true)).toEqual({
            ok: true,
            typo: true,
        });
    });

    it('rejects two typos', () => {
        expect(checkText('I am a stdnt', ['I am a student'], true)).toEqual({
            ok: false,
        });
    });

    it('prefers an exact match over a typo match', () => {
        expect(
            checkText(
                'I am a student',
                ['I am a studentt', 'I am a student'],
                true,
            ),
        ).toEqual({ ok: true });
    });
});

describe('speechScore', () => {
    it('is the share of target words heard in the best alternative', () => {
        expect(speechScore('I am a student.', ['I am a student'])).toBe(1);
        expect(speechScore('I am a student.', ['I am student'])).toBe(0.75);
        expect(
            speechScore('Nice to meet you', [
                'nice to eat you',
                'nice to meet you',
            ]),
        ).toBe(1);
    });

    it('compares contractions in full', () => {
        expect(speechScore("I'm fine", ['I am fine'])).toBe(1);
    });

    it('is 0 when nothing was heard', () => {
        expect(speechScore('Hello there', [])).toBe(0);
        expect(speechScore('Hello there', [''])).toBe(0);
    });
});

describe('speechDetail', () => {
    it('marks every word of the target as said or missed', () => {
        expect(speechDetail('Can you hear me?', ['can you see me'])).toEqual({
            score: 0.75,
            heard: 'can you see me',
            words: [
                { w: 'Can', hit: true },
                { w: 'you', hit: true },
                { w: 'hear', hit: false },
                { w: 'me?', hit: true },
            ],
        });
    });

    it('keeps the best alternative', () => {
        const detail = speechDetail('Good morning', [
            'good evening',
            'good morning',
        ]);
        expect(detail.score).toBe(1);
        expect(detail.heard).toBe('good morning');
    });

    it("needs both halves of a contraction (I'm = i am)", () => {
        expect(speechDetail("I'm ready", ['I ready']).words[0]).toEqual({
            w: "I'm",
            hit: false,
        });
    });

    it('counts punctuation-only tokens as said but leaves them out of the score', () => {
        const detail = speechDetail('Yes – of course', ['yes of course']);
        expect(detail.words[1]).toEqual({ w: '–', hit: true });
        expect(detail.score).toBe(1);
    });

    it('returns everything missed when nothing was heard', () => {
        expect(speechDetail('Hi there', [])).toEqual({
            score: -1,
            heard: '',
            words: [
                { w: 'Hi', hit: false },
                { w: 'there', hit: false },
            ],
        });
    });
});
