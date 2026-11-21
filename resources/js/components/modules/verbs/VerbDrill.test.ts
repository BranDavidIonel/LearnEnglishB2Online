// @vitest-environment happy-dom
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { trans } from '@/i18n';
import type { Replacements } from '@/i18n';
import VerbDrill from './VerbDrill.vue';
import type { DrillResult, VerbItem } from './verbs';

vi.mock('@/utils/speech', () => ({ speak: vi.fn(), stopSpeak: vi.fn() }));
vi.mock('@/utils/sound', () => ({ sfx: vi.fn() }));

const global = {
    mocks: {
        $t: (key: string, replacements?: Replacements) =>
            trans(key, replacements, 'en'),
    },
};

const verb = (v1: string, v2: string, v3: string): VerbItem => {
    const alts = (form: string): string[] =>
        form.split('/').map((alt) => alt.trim());
    const answers = (form: string): string[] => [
        ...alts(form),
        alts(form).join(' '),
        alts(form).join('/'),
        alts(form).join(' / '),
    ];

    return {
        v1,
        v2,
        v3,
        level: 'A1',
        ro: '',
        ipa: '',
        ex2: '',
        ex2Ro: '',
        ex3: '',
        ex3Ro: '',
        rank: 1,
        levelId: 'a1',
        status: 'new',
        box: null,
        due: null,
        known: false,
        pattern: 'abc',
        alts2: alts(v2),
        alts3: alts(v3),
        answers2: answers(v2),
        answers3: answers(v3),
    };
};

const be = verb('be', 'was / were', 'been');
const go = verb('go', 'went', 'gone');

let wrapper: VueWrapper | null = null;

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

function start(verbs: VerbItem[]): VueWrapper {
    wrapper = mount(VerbDrill, { props: { verbs, title: 'Drill' }, global });

    return wrapper;
}

async function answer(
    drill: VueWrapper,
    past: string,
    participle: string,
): Promise<void> {
    const inputs = drill.findAll('input');
    await inputs[0].setValue(past);
    await inputs[1].setValue(participle);
    await inputs[1].trigger('keydown', { key: 'Enter' });
}

async function next(drill: VueWrapper): Promise<void> {
    await drill.find('.btn.big').trigger('click');
}

describe('VerbDrill', () => {
    it('accepts any alternative or all of them, and both forms must be right', async () => {
        const drill = start([be, go]);

        await answer(drill, 'was / were', 'been');
        expect(drill.find('.feedback').classes()).toContain('good');
        await next(drill);

        await answer(drill, 'went', 'gonne');
        expect(drill.find('.feedback').classes()).toContain('bad');
        expect(drill.findAll('input')[0].classes()).toContain('right');
        expect(drill.findAll('input')[1].classes()).toContain('wrong');
        expect(drill.text()).toContain('It comes back once more at the end.');

        expect(drill.emitted('answer')).toEqual([
            [be, true],
            [go, false],
        ]);
    });

    it('asks for both forms before checking', async () => {
        const drill = start([go]);

        await drill.findAll('input')[0].setValue('went');
        await drill.find('.btn.primary.big').trigger('click');

        expect(drill.find('.feedback').exists()).toBe(false);
        expect(drill.emitted('answer')).toBeUndefined();
    });

    it('brings a missed verb back once at the end, without counting it again', async () => {
        const drill = start([go, be]);

        await answer(drill, 'goed', 'goed');
        expect(drill.find('.sess-count').text()).toBe('1 / 2');
        await next(drill);
        expect(drill.find('.sess-count').text()).toBe('2 / 2');
        await answer(drill, 'was', 'been');
        await next(drill);

        expect(drill.find('.sess-count').text()).toBe('Once more');
        await answer(drill, 'went', 'gone');
        await next(drill);

        const done = drill.emitted('done') as [DrillResult[], boolean][];
        expect(done).toHaveLength(1);
        expect(done[0][1]).toBe(false);
        expect(done[0][0].map((r) => [r.verb.v1, r.ok])).toEqual([
            ['go', false],
            ['be', true],
        ]);
        expect(drill.emitted('answer')).toHaveLength(2);
    });

    it('ends early with ✕ and hands over what was answered', async () => {
        const drill = start([go, be]);

        await answer(drill, 'went', 'gone');
        await drill.find('.sess-top .icon-btn').trigger('click');

        const done = drill.emitted('done') as [DrillResult[], boolean][];
        expect(done[0][1]).toBe(true);
        expect(done[0][0]).toHaveLength(1);
    });
});
