import { describe, expect, it } from 'vite-plus/test';
import type { VerbItem } from './verbs';
import { forms, mark, matchesSearch, spokenForms, usage } from './verbs';

const verb = (fields: Partial<VerbItem>): VerbItem => ({
    v1: 'go',
    v2: 'went',
    v3: 'gone',
    level: 'A1',
    ro: 'a merge, a pleca',
    ipa: '',
    ex2: '',
    ex2Ro: '',
    ex3: '',
    ex3Ro: '',
    rank: 5,
    levelId: 'a1',
    status: 'new',
    box: null,
    due: null,
    known: false,
    pattern: 'abc',
    alts2: ['went'],
    alts3: ['gone'],
    answers2: ['went'],
    answers3: ['gone'],
    ...fields,
});

const get = verb({
    v1: 'get',
    v2: 'got',
    v3: 'got / gotten',
    alts2: ['got'],
    alts3: ['got', 'gotten'],
});

describe('forms', () => {
    it('writes and reads the three forms', () => {
        expect(forms(get)).toBe('get – got – got / gotten');
        expect(spokenForms(get)).toBe('get. got. got or gotten.');
    });
});

describe('mark', () => {
    it('puts the first match of each alternative in bold, whole words only, any case', () => {
        expect(mark('Went home? We went.', ['went'])).toBe(
            '<strong>Went</strong> home? We went.',
        );
        expect(mark('She has gone to the gonelands.', ['gone'])).toBe(
            'She has <strong>gone</strong> to the gonelands.',
        );
    });

    it('escapes the sentence', () => {
        expect(mark('I <b>went</b>', ['went'])).toBe(
            'I &lt;b&gt;<strong>went</strong>&lt;/b&gt;',
        );
    });
});

describe('usage', () => {
    it('builds the examples from the forms of the verb', () => {
        expect(usage(get).map((row) => row.example)).toEqual([
            "I get · I will get · Did you get? · I didn't get",
            'I got (yesterday)',
            'I have got · I had got',
        ]);
    });

    it('has its own rows for "be"', () => {
        const be = verb({ v1: 'be', alts2: ['was', 'were'], alts3: ['been'] });

        expect(usage(be)[0]).toEqual({
            form: 'V1',
            when: 'present / future',
            example: 'I am · you are · she is · I will be',
        });
    });
});

describe('matchesSearch', () => {
    it('finds a verb by the start of any form or by its Romanian meaning', () => {
        expect(matchesSearch(get, '')).toBe(true);
        expect(matchesSearch(get, 'gott')).toBe(true);
        expect(matchesSearch(verb({}), 'wen')).toBe(true);
        expect(matchesSearch(verb({}), 'pleca')).toBe(true);
        expect(matchesSearch(verb({}), 'ent')).toBe(false);
    });
});
