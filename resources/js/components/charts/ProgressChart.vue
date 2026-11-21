<template>
    <div class="card chart-card">
        <div class="row between wrap">
            <div class="tabs chart-tabs">
                <button
                    v-for="item in metricTabs"
                    :key="item.id"
                    type="button"
                    class="tab"
                    :class="{ on: item.id === metric }"
                    @click="choose(item.id, range)"
                >
                    {{ item.tab }}
                </button>
            </div>
            <div class="chart-ranges">
                <button
                    v-for="item in rangeTabs"
                    :key="item.days"
                    type="button"
                    class="range"
                    :class="{ on: item.days === range }"
                    @click="choose(metric, item.days)"
                >
                    {{ item.label }}
                </button>
            </div>
        </div>

        <div class="chart-head">
            <div class="chart-value">
                {{ format(drawing.last) }}<small>{{ info.unit }}</small>
            </div>
            <div class="chart-change" :class="drawing.dir">
                <span class="arrow">{{ arrow }}</span>
                <span>{{ changeText }}</span>
            </div>
        </div>

        <div ref="plot" class="chart-plot">
            <svg
                :width="drawing.width"
                :height="drawing.totalHeight"
                :viewBox="`0 0 ${drawing.width} ${drawing.totalHeight}`"
                role="img"
                :aria-label="ariaLabel"
                @pointermove="move"
                @pointerdown="move"
                @pointerleave="leave"
            >
                <defs>
                    <linearGradient id="chart-fill" x1="0" y1="0" x2="0" y2="1">
                        <stop
                            offset="0"
                            :style="`stop-color:${drawing.color};stop-opacity:.32`"
                        />
                        <stop
                            offset="1"
                            :style="`stop-color:${drawing.color};stop-opacity:0`"
                        />
                    </linearGradient>
                </defs>
                <template v-for="line in drawing.grid" :key="`grid-${line.y}`">
                    <line
                        :x1="PAD_LEFT"
                        :x2="PAD_LEFT + drawing.plotWidth"
                        :y1="line.y"
                        :y2="line.y"
                        class="grid"
                    />
                    <text
                        :x="PAD_LEFT + drawing.plotWidth + 8"
                        :y="line.y + 4"
                        class="axis"
                    >
                        {{ line.label }}
                    </text>
                </template>
                <text
                    v-for="label in drawing.dayLabels"
                    :key="`day-${label.x}`"
                    :x="label.x"
                    :y="HEIGHT + 16"
                    class="axis"
                    :text-anchor="label.anchor"
                >
                    {{ label.text }}
                </text>
                <line
                    :x1="PAD_LEFT"
                    :x2="PAD_LEFT + drawing.plotWidth"
                    :y1="drawing.firstY"
                    :y2="drawing.firstY"
                    class="ref"
                />
                <template v-if="drawing.linePath">
                    <path :d="drawing.areaPath" fill="url(#chart-fill)" />
                    <path
                        :d="drawing.linePath"
                        fill="none"
                        :stroke="drawing.color"
                        stroke-width="2"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                </template>
                <circle
                    :cx="drawing.lastX"
                    :cy="drawing.lastY"
                    r="5"
                    :fill="drawing.color"
                    class="ring"
                />

                <text :x="PAD_LEFT" :y="HEIGHT + GAP_Y - 6" class="axis strong">
                    {{ $t('XP per day') }}
                </text>
                <line
                    :x1="PAD_LEFT"
                    :x2="PAD_LEFT + drawing.plotWidth"
                    :y1="drawing.goalY"
                    :y2="drawing.goalY"
                    class="ref"
                />
                <text
                    :x="PAD_LEFT + drawing.plotWidth + 8"
                    :y="drawing.goalY + 4"
                    class="axis"
                >
                    {{ $t('goal :n', { n: data.goal }) }}
                </text>
                <line
                    :x1="PAD_LEFT"
                    :x2="PAD_LEFT + drawing.plotWidth"
                    :y1="drawing.barBase"
                    :y2="drawing.barBase"
                    class="grid"
                />
                <template v-for="(bar, index) in drawing.bars" :key="index">
                    <path
                        v-if="bar"
                        :d="bar"
                        class="bar"
                        :class="{ hot: index === hover }"
                    />
                </template>

                <line
                    :x1="hoverX"
                    :x2="hoverX"
                    :y1="TOP"
                    :y2="drawing.barBase"
                    class="cross"
                    :visibility="hover === null ? 'hidden' : 'visible'"
                />
                <circle
                    :cx="hoverX"
                    :cy="
                        hoverPoint && hoverPoint.v !== null
                            ? y(hoverPoint.v)
                            : 0
                    "
                    r="5"
                    :fill="drawing.color"
                    class="ring"
                    :visibility="
                        hoverPoint && hoverPoint.v !== null
                            ? 'visible'
                            : 'hidden'
                    "
                />
            </svg>
            <div
                ref="tip"
                class="chart-tip"
                :class="{ hidden: !hoverPoint }"
                :style="{ left: `${tipLeft}px`, top: `${tipTop}px` }"
            >
                <template v-if="hoverPoint">
                    <div class="tip-date">
                        {{ dayLabel(hoverPoint.d, true) }}
                    </div>
                    <div v-if="hoverPoint.v !== null" class="tip-val">
                        {{ format(hoverPoint.v) }}{{ info.unit
                        }}<span v-if="hoverDelta" class="tip-d">{{
                            deltaText(hoverDelta)
                        }}</span>
                    </div>
                    <div v-else class="tip-xp">
                        {{ $t('no data for this measure') }}
                    </div>
                    <div class="tip-xp">
                        {{ $t(':xp XP that day', { xp: hoverPoint.xp }) }}
                    </div>
                </template>
            </div>
        </div>

        <p class="muted small">{{ note }}</p>

        <details class="script chart-table">
            <summary>{{ $t('📋 Show the data as a table') }}</summary>
            <div class="table-wrap">
                <table class="g-table">
                    <thead>
                        <tr>
                            <th>{{ $t('Day') }}</th>
                            <th>{{ info.what }}</th>
                            <th>{{ $t('XP that day') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in tableRows" :key="row.d">
                            <td>{{ dayLabel(row.d, true) }}</td>
                            <td>{{ format(row.v ?? 0) }}{{ info.unit }}</td>
                            <td>{{ row.xp }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { ChartData } from '@/components/modules/home/types';
import { intlLocale } from '@/i18n';
import { today } from '@/utils/dates';

type MetricId = 'xp' | 'w' | 'v' | 'b1' | 'b2';

type Metric = {
    id: MetricId;
    tab: string;
    /** Shown after the value: " XP", "%" or nothing. */
    unit: string;
    /** What the line measures (tooltip, table, screen readers). */
    what: string;
    /** Percentage points: one decimal, changes in points rather than in %. */
    pp: boolean;
};

/** A day of the line: v = the measure at the end of that day (null before the history starts), xp = XP earned that day. */
type Point = { d: string; v: number | null; xp: number };

type Drawing = {
    width: number;
    totalHeight: number;
    plotWidth: number;
    band: number;
    lo: number;
    hi: number;
    first: number;
    last: number;
    dir: 'up' | 'down' | 'flat';
    color: string;
    grid: { y: number; label: string }[];
    dayLabels: { x: number; text: string; anchor: string }[];
    firstY: number;
    lastX: number;
    lastY: number;
    areaPath: string;
    linePath: string;
    goalY: number;
    barBase: number;
    bars: (string | null)[];
};

const METRIC_IDS: MetricId[] = ['xp', 'w', 'v', 'b1', 'b2'];
// Days of each range button; 0 = everything since the first day with data.
const RANGES = [7, 30, 90, 0];
// Geometry of the original chart: paddings, the line's height, the gap and the height of the XP strip.
const PAD_LEFT = 6;
const PAD_RIGHT = 54;
const TOP = 14;
const HEIGHT = 250;
const GAP_Y = 36;
const STRIP = 54;
// The chosen measure and range are remembered in this browser, under the original key.
const STORAGE_KEY = 'el-chart';

/** A "nice" grid step (1, 2, 2.5, 5 or 10 × a power of ten) for about `count` lines over `span`. */
function niceStep(span: number, count: number): number {
    const raw = span / count;
    const magnitude = 10 ** Math.floor(Math.log10(raw));
    const fraction = raw / magnitude;
    const nice =
        fraction <= 1
            ? 1
            : fraction <= 2
              ? 2
              : fraction <= 2.5
                ? 2.5
                : fraction <= 5
                  ? 5
                  : 10;

    return nice * magnitude;
}

/** The last `count` days, oldest first, as local "YYYY-MM-DD". */
function dayKeys(count: number): string[] {
    const keys: string[] = [];

    for (let ago = count - 1; ago >= 0; ago--) {
        const date = new Date();
        date.setDate(date.getDate() - ago);
        keys.push(today(date));
    }

    return keys;
}

function localDate(key: string): Date {
    const [year, month, day] = key.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function savedChoice(): { metric: MetricId; range: number } {
    const choice = { metric: 'xp' as MetricId, range: 30 };

    try {
        const saved: unknown = JSON.parse(
            localStorage.getItem(STORAGE_KEY) || '{}',
        );

        if (saved && typeof saved === 'object') {
            const { metric, range } = saved as Record<string, unknown>;

            if (METRIC_IDS.includes(metric as MetricId)) {
                choice.metric = metric as MetricId;
            }

            if (RANGES.includes(range as number)) {
                choice.range = range as number;
            }
        }
    } catch {
        // A broken or blocked storage keeps the defaults.
    }

    return choice;
}

/**
 * The progress chart ("like a price chart"): one measure over time, coloured by its
 * direction, with a crosshair tooltip, range buttons, an "XP per day" strip underneath
 * and the same data as a table. Drawn by hand in SVG, like the original.
 */
export default defineComponent({
    name: 'ProgressChart',

    props: {
        data: { type: Object as PropType<ChartData>, required: true },
    },

    data() {
        return {
            ...savedChoice(),
            PAD_LEFT,
            TOP,
            HEIGHT,
            GAP_Y,
            /** Width of the plot in pixels, 0 until measured. */
            plotWidth: 0,
            /** Index of the day under the pointer. */
            hover: null as number | null,
            tipLeft: 0,
            tipTop: 0,
            observer: null as ResizeObserver | null,
        };
    },

    computed: {
        locale(): string {
            return intlLocale(String(this.$page.props.locale));
        },

        metrics(): Record<MetricId, Metric> {
            const road = (level: string): string =>
                this.$t('Progress towards :level', { level });

            return {
                xp: {
                    id: 'xp',
                    tab: this.$t('Total XP'),
                    unit: ' XP',
                    what: this.$t('XP earned'),
                    pp: false,
                },
                w: {
                    id: 'w',
                    tab: this.$t('Words learned'),
                    unit: '',
                    what: this.$t('Words learned'),
                    pp: false,
                },
                v: {
                    id: 'v',
                    tab: this.$t('Irregular verbs'),
                    unit: '',
                    what: this.$t('Irregular verbs learned'),
                    pp: false,
                },
                b1: {
                    id: 'b1',
                    tab: this.$t('Road to B1'),
                    unit: '%',
                    what: road('B1'),
                    pp: true,
                },
                b2: {
                    id: 'b2',
                    tab: this.$t('Road to B2'),
                    unit: '%',
                    what: road('B2'),
                    pp: true,
                },
            };
        },

        metricTabs(): Metric[] {
            return METRIC_IDS.map((id) => this.metrics[id]);
        },

        rangeTabs(): { days: number; label: string }[] {
            return [
                { days: 7, label: this.$t('7D') },
                { days: 30, label: this.$t('30D') },
                { days: 90, label: this.$t('90D') },
                { days: 0, label: this.$t('All') },
            ];
        },

        info(): Metric {
            return this.metrics[this.metric];
        },

        /** How many days "All" covers: from the first day with data, at least a week. */
        allDays(): number {
            const keys = [
                ...Object.keys(this.data.days),
                ...Object.keys(this.data.history),
            ].sort();

            if (!keys.length) {
                return 7;
            }

            const noon = new Date().setHours(12, 0, 0, 0);
            const start = localDate(keys[0]).setHours(12);

            return Math.max(7, Math.round((noon - start) / 864e5) + 1);
        },

        series(): Point[] {
            const dates = dayKeys(this.range || this.allDays);
            const days = this.data.days;
            const points =
                this.metric === 'xp'
                    ? this.runningTotal(dates)
                    : this.snapshots(dates);

            // Today always has a value, so the line ends on today.
            const last = points[points.length - 1];

            if (last.v === null) {
                last.v = 0;
            }

            return points.map((point) => ({
                ...point,
                xp: days[point.d] || 0,
            }));
        },

        /** The days with a value, with their place in the series. */
        known(): (Point & { i: number; v: number })[] {
            return this.series
                .map((point, i) => ({ ...point, i }))
                .filter(
                    (point): point is Point & { i: number; v: number } =>
                        point.v !== null,
                );
        },

        drawing(): Drawing {
            const points = this.series;
            const known = this.known;
            const width = Math.max(320, this.plotWidth || 720);
            const plotWidth = width - PAD_LEFT - PAD_RIGHT;
            const band = plotWidth / points.length;
            const values = known.map((point) => point.v);
            const first = values[0];
            const last = values[values.length - 1];
            const delta = last - first;
            const dir = delta > 0 ? 'up' : delta < 0 ? 'down' : 'flat';
            let lo = Math.min(...values);
            let hi = Math.max(...values);

            if (hi === lo) {
                hi += 1;
                lo = Math.max(0, lo - 1);
            }

            const step = niceStep(hi - lo, 4);
            lo = Math.max(0, Math.floor(lo / step) * step);
            hi = Math.ceil(hi / step) * step;

            const x = (i: number): number => PAD_LEFT + (i + 0.5) * band;
            const y = (v: number): number =>
                TOP + (1 - (v - lo) / (hi - lo)) * (HEIGHT - TOP);
            const grid: Drawing['grid'] = [];

            for (let v = lo; v <= hi + step / 1e6; v += step) {
                grid.push({
                    y: y(v),
                    label: this.info.pp ? this.decimal(v) : this.integer(v),
                });
            }

            // About five day labels, always including the last day.
            const every = Math.max(1, Math.ceil(points.length / 5));
            const dayLabels: Drawing['dayLabels'] = [];

            for (let i = points.length - 1; i >= 0; i -= every) {
                dayLabels.push({
                    x: x(i),
                    text: this.dayLabel(points[i].d),
                    anchor: i === points.length - 1 ? 'end' : 'middle',
                });
            }

            const coords = known.map(
                (point) => `${x(point.i).toFixed(1)},${y(point.v).toFixed(1)}`,
            );
            const lastX = x(points.length - 1);
            const barBase = HEIGHT + GAP_Y + STRIP;
            const maxXp = Math.max(
                this.data.goal,
                ...points.map((point) => point.xp),
            );

            return {
                width,
                totalHeight: HEIGHT + GAP_Y + STRIP + 22,
                plotWidth,
                band,
                lo,
                hi,
                first,
                last,
                dir,
                color: `var(--${dir === 'flat' ? 'flatc' : dir})`,
                grid,
                dayLabels,
                firstY: y(first),
                lastX,
                lastY: y(last),
                // The area under the line and the line itself (from two known days on).
                areaPath:
                    known.length > 1
                        ? `M${x(known[0].i).toFixed(1)},${HEIGHT} L${coords.join(' L')} L${lastX.toFixed(1)},${HEIGHT} Z`
                        : '',
                linePath: known.length > 1 ? `M${coords.join(' L')}` : '',
                goalY: barBase - (this.data.goal / maxXp) * STRIP,
                barBase,
                bars: points.map((point, i) =>
                    this.barPath(point.xp, maxXp, x(i), band, barBase),
                ),
            };
        },

        arrow(): string {
            return { up: '▲', down: '▼', flat: '■' }[this.drawing.dir];
        },

        sign(): string {
            return { up: '+', down: '−', flat: '' }[this.drawing.dir];
        },

        period(): string {
            return this.range
                ? this.$t('in the last :n days', { n: this.range })
                : this.$t('since the start');
        },

        /** "+120 XP (+12.5%) in the last 30 days"; the percentage only when it means something. */
        changeText(): string {
            const { first, last } = this.drawing;
            const delta = last - first;
            const change = this.info.pp ? ` ${this.$t('pts')}` : this.info.unit;
            const raw =
                !this.info.pp && first > 0 ? (delta / first) * 100 : null;
            // "+3840%" from a tiny start says nothing.
            const percent =
                raw !== null && Math.abs(raw) < 1000
                    ? ` (${this.sign}${this.decimal(Math.abs(raw))}%)`
                    : '';

            return `${this.sign}${this.format(Math.abs(delta))}${change}${percent} ${this.period}`;
        },

        ariaLabel(): string {
            return this.$t(':what, :period: from :first to :last:unit', {
                what: this.info.what,
                period: this.period,
                first: this.format(this.drawing.first),
                last: this.format(this.drawing.last),
                unit: this.info.unit,
            });
        },

        note(): string {
            if (this.known.length < 2 && this.metric !== 'xp') {
                return this.$t(
                    'The history for this measure starts today — the chart fills in day by day as you learn.',
                );
            }

            return this.metric === 'w'
                ? this.$t(
                      'The line can also go down: a word leaves "learned" if you forget it in the flashcards.',
                  )
                : '';
        },

        hoverPoint(): Point | null {
            return this.hover === null
                ? null
                : (this.series[this.hover] ?? null);
        },

        hoverX(): number {
            return this.hover === null
                ? 0
                : PAD_LEFT + (this.hover + 0.5) * this.drawing.band;
        },

        /** Change from the day before, 0 when either day has no value. */
        hoverDelta(): number {
            const point = this.hoverPoint;
            const before =
                this.hover === null ? undefined : this.series[this.hover - 1];

            return point && point.v !== null && before && before.v !== null
                ? point.v - before.v
                : 0;
        },

        /** The last 60 days with a value, newest first. */
        tableRows(): Point[] {
            return this.known.slice(-60).reverse();
        },
    },

    mounted() {
        const plot = this.$refs.plot as HTMLElement;

        this.plotWidth = plot.clientWidth;
        this.observer = new ResizeObserver(() => {
            const width = plot.clientWidth;

            // Redraw only for a real change, so a scroll bar coming and going does not loop.
            if (width && Math.abs(width - this.plotWidth) > 4) {
                this.plotWidth = width;
                this.hover = null;
            }
        });
        this.observer.observe(plot);
    },

    beforeUnmount() {
        this.observer?.disconnect();
    },

    methods: {
        choose(metric: MetricId, range: number): void {
            this.metric = metric;
            this.range = range;
            this.hover = null;

            try {
                localStorage.setItem(
                    STORAGE_KEY,
                    JSON.stringify({ metric, range }),
                );
            } catch {
                // Not remembered in a private window; the choice still applies.
            }
        },

        /** XP: the running total of the XP of every day up to each date. */
        runningTotal(dates: string[]): Point[] {
            const days = this.data.days;
            const keys = Object.keys(days).sort();
            let total = 0;
            let next = 0;

            return dates.map((d) => {
                while (next < keys.length && keys[next] <= d) {
                    total += days[keys[next++]];
                }

                return { d, v: total, xp: 0 };
            });
        },

        /** Other measures: the latest snapshot up to each date; days before the first one stay empty. */
        snapshots(dates: string[]): Point[] {
            const history = this.data.history;
            const keys = Object.keys(history).sort();
            const metric = this.metric as Exclude<MetricId, 'xp'>;
            let value: number | null = null;
            let next = 0;

            return dates.map((d) => {
                while (next < keys.length && keys[next] <= d) {
                    value = history[keys[next++]][metric] ?? value;
                }

                return { d, v: value, xp: 0 };
            });
        },

        /** A bar of the XP strip with rounded top corners; null for a day without XP. */
        barPath(
            xp: number,
            maxXp: number,
            center: number,
            band: number,
            base: number,
        ): string | null {
            if (!xp) {
                return null;
            }

            const width = Math.max(2, Math.min(18, band - 2));
            const height = Math.max(3, (xp / maxXp) * STRIP);
            const left = center - width / 2;
            const top = base - height;
            const r = Math.min(Math.min(4, width / 2), height);

            return `M${left},${base} V${top + r} Q${left},${top} ${left + r},${top} H${left + width - r} Q${left + width},${top} ${left + width},${top + r} V${base} Z`;
        },

        y(v: number): number {
            const { lo, hi } = this.drawing;

            return TOP + (1 - (v - lo) / (hi - lo)) * (HEIGHT - TOP);
        },

        integer(v: number): string {
            return Math.round(v).toLocaleString(this.locale);
        },

        decimal(v: number): string {
            return v.toLocaleString(this.locale, {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1,
            });
        },

        format(v: number): string {
            return this.info.pp ? this.decimal(v) : this.integer(v);
        },

        deltaText(delta: number): string {
            const points = this.info.pp ? ` ${this.$t('pts')}` : '';

            return ` ${delta > 0 ? '▲ +' : '▼ −'}${this.format(Math.abs(delta))}${points}`;
        },

        dayLabel(key: string, long = false): string {
            const options: Intl.DateTimeFormatOptions = long
                ? { weekday: 'short', day: 'numeric', month: 'short' }
                : { day: 'numeric', month: 'short' };

            return localDate(key).toLocaleDateString(this.locale, options);
        },

        /** Moves the crosshair to the day under the pointer and places the tooltip beside it. */
        async move(event: PointerEvent): Promise<void> {
            const svg = event.currentTarget as SVGSVGElement;
            const rect = svg.getBoundingClientRect();
            const { width, band, totalHeight } = this.drawing;
            const index = Math.floor(
                ((event.clientX - rect.left) * (width / rect.width) -
                    PAD_LEFT) /
                    band,
            );

            this.hover = Math.max(0, Math.min(this.series.length - 1, index));
            await this.$nextTick();

            const tip = this.$refs.tip as HTMLElement | undefined;
            const point = this.hoverPoint;

            if (!tip || !point) {
                return;
            }

            const px = (this.hoverX / width) * rect.width;
            const left =
                px > rect.width / 2 ? px - tip.offsetWidth - 12 : px + 12;
            const anchor = point.v !== null ? this.y(point.v) : TOP + 40;

            this.tipLeft = Math.max(
                0,
                Math.min(rect.width - tip.offsetWidth, left),
            );
            this.tipTop = Math.max(
                0,
                (anchor / totalHeight) * rect.height - tip.offsetHeight - 10,
            );
        },

        leave(): void {
            this.hover = null;
        },
    },
});
</script>
