<template>
    <div class="week">
        <div
            v-for="(day, index) in bars"
            :key="day.date"
            class="week-day"
            :class="{ today: index === bars.length - 1 }"
        >
            <div class="week-xp">{{ day.xp || '' }}</div>
            <div class="week-bar">
                <div
                    class="week-fill"
                    :class="{ goal: day.xp >= goal }"
                    :style="{ height: `${day.height}%` }"
                ></div>
            </div>
            <div class="week-label">{{ day.label }}</div>
        </div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';

type Day = { date: string; xp: number };
type Bar = Day & { label: string; height: number };

// One-letter day names, Sunday first (Date.getDay()). They are not translation keys:
// English repeats letters (S, T) that Romanian writes differently (D/S, Ma/J).
const DAY_LETTERS: Record<string, string[]> = {
    en: ['S', 'M', 'T', 'W', 'T', 'F', 'S'],
    ro: ['D', 'L', 'Ma', 'Mi', 'J', 'V', 'S'],
};

/** XP of the last seven days as bars; green where the daily goal was reached, today in bold. */
export default defineComponent({
    name: 'WeekChart',

    props: {
        /** The last 7 days, oldest first; the last one is today. */
        days: { type: Array as PropType<Day[]>, required: true },
        /** Daily XP goal. */
        goal: { type: Number, required: true },
    },

    computed: {
        bars(): Bar[] {
            const letters =
                DAY_LETTERS[String(this.$page.props.locale)] ?? DAY_LETTERS.en;
            const max = Math.max(this.goal, ...this.days.map((day) => day.xp));

            return this.days.map((day) => ({
                ...day,
                label: letters[weekday(day.date)],
                // A day with a little XP still shows a sliver.
                height: max
                    ? Math.max(day.xp ? 6 : 0, (day.xp / max) * 100)
                    : 0,
            }));
        },
    },
});

/** Day of the week of a "YYYY-MM-DD" date, read as a local date (0 = Sunday). */
function weekday(date: string): number {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day).getDay();
}
</script>
