// @vitest-environment happy-dom
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import ProgressChart from '@/components/charts/ProgressChart.vue';
import type { ChartData } from '@/components/modules/home/types';
import { trans } from '@/i18n';
import type { Replacements } from '@/i18n';

const global = {
    mocks: {
        $t: (key: string, replacements?: Replacements) =>
            trans(key, replacements, 'en'),
        $page: { props: { locale: 'en' } },
    },
};

let wrapper: VueWrapper | null = null;

function draw(data: ChartData): VueWrapper {
    wrapper = mount(ProgressChart, {
        props: { data },
        global,
        attachTo: document.body,
    });

    return wrapper;
}

const tab = (chart: VueWrapper, text: string) =>
    chart.findAll('button').find((button) => button.text() === text);

describe('ProgressChart', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 10, 21, 10));
        localStorage.clear();
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
        vi.useRealTimers();
    });

    it('draws the total XP of the last 30 days by default', () => {
        const chart = draw({
            days: { '2026-10-01': 100, '2026-11-01': 20, '2026-11-20': 30 },
            history: {},
            goal: 50,
        });

        expect(tab(chart, 'Total XP')?.classes()).toContain('on');
        expect(tab(chart, '30D')?.classes()).toContain('on');
        expect(chart.find('.chart-value').text()).toBe('150 XP');
        // 100 XP before the range, 150 at the end: +50 (+50.0%).
        expect(chart.find('.chart-change').classes()).toContain('up');
        expect(chart.find('.chart-change').text()).toBe(
            '▲+50 XP (+50.0%) in the last 30 days',
        );
        // One bar per day with XP inside the range.
        expect(chart.findAll('path.bar')).toHaveLength(2);
        expect(chart.find('text.axis.strong').text()).toBe('XP per day');
    });

    it('ends the line on zero and explains a measure without history', async () => {
        const chart = draw({ days: {}, history: {}, goal: 50 });

        await tab(chart, 'Words learned')?.trigger('click');

        expect(chart.find('.chart-value').text()).toBe('0');
        expect(chart.find('.chart-change').classes()).toContain('flat');
        expect(chart.find('p.muted.small').text()).toContain(
            'The history for this measure starts today',
        );
        expect(localStorage.getItem('el-chart')).toBe(
            JSON.stringify({ metric: 'w', range: 30 }),
        );
    });

    it('reads the road to B1 from the snapshots, in points', async () => {
        const chart = draw({
            days: {},
            history: {
                '2026-11-10': { xp: 10, b1: 10 },
                '2026-11-21': { xp: 20, b1: 12.5 },
            },
            goal: 50,
        });

        await tab(chart, 'Road to B1')?.trigger('click');
        await tab(chart, 'All')?.trigger('click');

        expect(chart.find('.chart-value').text()).toBe('12.5%');
        expect(chart.find('.chart-change').text()).toBe(
            '▲+2.5 pts since the start',
        );
        expect(chart.findAll('.chart-table tbody tr')).toHaveLength(12);
    });

    it('remembers the chosen measure and range', () => {
        localStorage.setItem(
            'el-chart',
            JSON.stringify({ metric: 'v', range: 7 }),
        );
        const chart = draw({ days: {}, history: {}, goal: 50 });

        expect(tab(chart, 'Irregular verbs')?.classes()).toContain('on');
        expect(tab(chart, '7D')?.classes()).toContain('on');
    });

    it('shows the day under the pointer', async () => {
        const chart = draw({
            days: { '2026-11-21': 12 },
            history: {},
            goal: 50,
        });
        const svg = chart.find('svg');

        await tab(chart, '7D')?.trigger('click');
        vi.spyOn(svg.element, 'getBoundingClientRect').mockReturnValue(
            new DOMRect(0, 0, 720, 362),
        );
        await svg.trigger('pointermove', { clientX: 700 });

        expect(chart.find('.chart-tip').classes()).not.toContain('hidden');
        expect(chart.find('.tip-xp').text()).toBe('12 XP that day');
        expect(chart.find('path.bar').classes()).toContain('hot');

        await svg.trigger('pointerleave');

        expect(chart.find('.chart-tip').classes()).toContain('hidden');
    });
});
