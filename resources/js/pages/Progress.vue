<template>
    <Head :title="$t('Progress')" />
    <PageHeader :title="$t('Progress')" />
    <ProgressChart :data="chart" />
    <RoadCard :road="road" />
    <div class="stats">
        <div class="card stat">
            <div class="stat-icon">💎</div>
            <div class="stat-num">{{ $learner.xp }}</div>
            <div class="muted">{{ $t('total XP') }}</div>
        </div>
        <div class="card stat">
            <div class="stat-icon">🔥</div>
            <div class="stat-num">{{ $learner.streak }}</div>
            <div class="muted">
                {{ $t('day streak', { count: $learner.streak }) }}
            </div>
        </div>
        <div class="card stat">
            <div class="stat-icon">📗</div>
            <div class="stat-num">
                {{ stats.units.passed }}/{{ stats.units.total }}
            </div>
            <div class="muted">{{ $t('units passed') }}</div>
        </div>
        <div class="card stat">
            <div class="stat-icon">📖</div>
            <div class="stat-num">{{ stats.words.learned }}</div>
            <div class="muted">
                {{
                    $t('learned · :learning in progress · :new new', {
                        learning: stats.words.learning,
                        new: stats.words.new,
                    })
                }}
            </div>
        </div>
        <Link class="card stat link" :href="verbsUrl">
            <div class="stat-icon">🔁</div>
            <div class="stat-num">
                {{ stats.verbs.learned }}/{{ stats.verbs.total }}
            </div>
            <div class="muted">{{ $t('irregular verbs learned') }}</div>
        </Link>
    </div>
    <div class="card">
        <h3>{{ $t('🏅 Achievements') }}</h3>
        <div class="badges">
            <div
                v-for="badge in badges"
                :key="badge.name"
                class="badge"
                :class="{ ok: badge.ok }"
                :title="badge.desc"
            >
                <div class="badge-icon">{{ badge.icon }}</div>
                <strong>{{ badge.name }}</strong>
                <small class="muted">{{ badge.desc }}</small>
            </div>
        </div>
    </div>
    <Link class="card exam-card" :href="settingsUrl">
        <div class="stat-icon">⚙️</div>
        <div class="grow">
            <h3>{{ $t('Settings') }}</h3>
            <p class="muted">
                {{ $t('Colour theme, app language, voice, daily goal.') }}
            </p>
        </div>
        <span class="btn">{{ $t('Open →') }}</span>
    </Link>
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ProgressChart from '@/components/charts/ProgressChart.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import RoadCard from '@/components/modules/home/RoadCard.vue';
import type {
    Badge,
    ChartData,
    ProgressStats,
    Road,
} from '@/components/modules/home/types';
import { settings, verbs } from '@/routes';

/** The progress page: the chart, the road to B2, the totals, the achievements and a way to the settings. */
export default defineComponent({
    name: 'ProgressPage',

    components: {
        Head,
        Link,
        PageHeader,
        ProgressChart,
        RoadCard,
    },

    props: {
        chart: { type: Object as PropType<ChartData>, required: true },
        road: { type: Object as PropType<Road>, required: true },
        stats: { type: Object as PropType<ProgressStats>, required: true },
        badges: { type: Array as PropType<Badge[]>, required: true },
    },

    computed: {
        verbsUrl(): string {
            return verbs().url;
        },

        settingsUrl(): string {
            return settings().url;
        },
    },
});
</script>
