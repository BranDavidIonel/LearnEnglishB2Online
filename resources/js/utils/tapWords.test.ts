// @vitest-environment happy-dom
import { describe, expect, it } from 'vite-plus/test';
import { tapTarget, tapWords, sentenceAt, lookupText } from './tapWords';
import { esc, gap, md } from './text';

/** The words a text gets as tappable, in order. */
function tapped(html: string): string[] {
    return [
        ...tapWords(html).matchAll(/<span class="tap-w">(.*?)<\/span>/g),
    ].map((m) => m[1]);
}

describe('tapWords', () => {
    it('marks every word and leaves spaces and punctuation outside', () => {
        expect(tapWords('Hi, Tom!')).toBe(
            '<span class="tap-w">Hi</span>, <span class="tap-w">Tom</span>!',
        );
    });

    it('keeps contractions whole, with straight and curly apostrophes', () => {
        expect(tapped(esc("I don't know, it’s fine"))).toEqual([
            'I',
            'don&#39;t',
            'know',
            'it’s',
            'fine',
        ]);
    });

    it('leaves an apostrophe or a hyphen at the edge of a word outside it', () => {
        expect(tapped(esc("'Hello' - the teachers' room, -ing"))).toEqual([
            'Hello',
            'the',
            'teachers',
            'room',
            'ing',
        ]);
    });

    it('keeps hyphenated words whole', () => {
        expect(tapped('a start-up in a four-five-six building')).toEqual([
            'a',
            'start-up',
            'in',
            'a',
            'four-five-six',
            'building',
        ]);
    });

    it('takes letters of any alphabet: names with diacritics stay one word', () => {
        expect(tapped('From Iași to Brașov with Graça')).toEqual([
            'From',
            'Iași',
            'to',
            'Brașov',
            'with',
            'Graça',
        ]);
    });

    it('splits abbreviations at their dots', () => {
        expect(tapped('at 5 p.m., e.g. now')).toEqual([
            'at',
            'p',
            'm',
            'e',
            'g',
            'now',
        ]);
    });

    it('never marks numbers or letters touching a digit', () => {
        expect(tapped('the 12th of May, T1, B2, 3D and 2024')).toEqual([
            'the',
            'of',
            'May',
            'and',
        ]);
    });

    it('leaves the gap of a question and what touches it alone', () => {
        expect(tapped(gap('She ___ (go) home. I wait___ed.'))).toEqual([
            'She',
            'go',
            'home',
            'I',
        ]);
    });

    it('works inside bold and italic markdown without touching the tags', () => {
        expect(tapWords(md('**Bold** and *italic*'))).toBe(
            '<strong><span class="tap-w">Bold</span></strong> <span class="tap-w">and</span> <em><span class="tap-w">italic</span></em>',
        );
    });

    it('copies entities as they are', () => {
        expect(tapWords(esc('Tom & Jerry <3 "quotes"'))).toBe(
            '<span class="tap-w">Tom</span> &amp; <span class="tap-w">Jerry</span> &lt;3 &quot;<span class="tap-w">quotes</span>&quot;',
        );
    });

    it('marks a word next to punctuation without the punctuation', () => {
        expect(tapWords('(yes), no; maybe… “sure”')).toBe(
            '(<span class="tap-w">yes</span>), <span class="tap-w">no</span>; <span class="tap-w">maybe</span>… “<span class="tap-w">sure</span>”',
        );
    });

    it('never marks a word twice', () => {
        const once = tapWords(md("Don't **stop** now"));

        expect(tapWords(once)).toBe(once);
    });

    it('keeps line breaks', () => {
        expect(tapWords(md('One.\nTwo.'))).toBe(
            '<span class="tap-w">One</span>.<br><span class="tap-w">Two</span>.',
        );
    });
});

describe('lookupText', () => {
    it('keeps the case and straightens a curly apostrophe', () => {
        expect(lookupText(' It’s ')).toBe("It's");
    });
});

describe('sentenceAt', () => {
    const text =
        'I was on call. The alerts started at 3 a.m.! Was it bad?\nYes';

    it('finds the sentence around a position', () => {
        expect(sentenceAt(text, 0)).toBe('I was on call.');
        expect(sentenceAt(text, text.indexOf('alerts'))).toBe(
            'The alerts started at 3 a.m.!',
        );
        expect(sentenceAt(text, text.indexOf('bad'))).toBe('Was it bad?');
        expect(sentenceAt(text, text.indexOf('Yes'))).toBe('Yes');
    });

    it('keeps closing quotes and tidies spaces', () => {
        expect(sentenceAt('He said "Go home."  Then   left.', 3)).toBe(
            'He said "Go home."',
        );
        expect(sentenceAt('He said "Go home."  Then   left.', 22)).toBe(
            'Then left.',
        );
    });
});

describe('tapTarget', () => {
    function render(html: string): HTMLElement {
        const root = document.createElement('div');
        root.innerHTML = html;
        document.body.replaceChildren(root);

        return root;
    }

    it('reads the word and its sentence from the closest tappable text', () => {
        const root = render(
            `<div class="line"><span class="spk">Ana</span><div class="en tap">${tapWords(
                md("I'm on call. The **alerts** started."),
            )}</div></div>`,
        );
        const words = root.querySelectorAll('.tap-w');

        expect(tapTarget(words[0])).toEqual({
            word: "I'm",
            sentence: "I'm on call.",
        });
        expect(tapTarget(words[4])).toEqual({
            word: 'alerts',
            sentence: 'The alerts started.',
        });
    });

    it('ends a sentence at a line break and falls back to the parent', () => {
        const root = render(
            `<p>${tapWords(md('First line\nsecond line'))}</p>`,
        );
        const words = root.querySelectorAll('.tap-w');

        expect(tapTarget(words[3])).toEqual({
            word: 'line',
            sentence: 'second line',
        });
    });

    it('finds the right sentence when the same word comes twice', () => {
        const root = render(
            `<p class="tap">${tapWords('Go now. Go later.')}</p>`,
        );
        const words = root.querySelectorAll('.tap-w');

        expect(tapTarget(words[2])?.sentence).toBe('Go later.');
    });
});
