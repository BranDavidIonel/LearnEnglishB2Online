<template>
    <div class="card ready" :class="verdict.kind">
        <div class="row wrap gap-lg">
            <ProgressRing
                :value="readiness.overall ?? 0"
                :color="verdict.color"
                :label="
                    readiness.overall === null
                        ? '?'
                        : `${percent(readiness.overall)}%`
                "
                :size="96"
            />
            <div class="grow">
                <div class="muted">
                    {{ $t('Am I ready for :name?', { name: exam.name }) }}
                </div>
                <h2>{{ verdict.label }}</h2>
                <p>{{ verdict.text }}</p>
            </div>
        </div>

        <div class="ready-grid">
            <div v-for="paper in papers" :key="paper" class="ready-paper">
                <div class="row between">
                    <span>
                        {{ paperIcons[paper] }} {{ paperName(exam.id, paper) }}
                    </span>
                    <strong>{{ latestLabel(paper) }}</strong>
                </div>
                <ProgressBar
                    :value="readiness.latest[paper]?.score ?? 0"
                    :color="latestColor(paper)"
                    :target="target"
                />
                <small class="muted">{{ latestNote(paper) }}</small>
            </div>
        </div>

        <div class="to-goal">
            <div class="row between">
                <span>
                    {{
                        $t('Course up to and including :level', {
                            level: exam.level,
                        })
                    }}
                </span>
                <strong>{{ percent(readiness.course) }}%</strong>
            </div>
            <ProgressBar :value="readiness.course" />
        </div>

        <p class="muted small">
            {{
                $t(
                    "The safe target is :pct% on each paper (the vertical line). The real exam's pass mark is about 60–65%, but nerves and real voices usually lower your score. Writing and Speaking are self-assessments, so treat them as a rough guide.",
                    { pct: percent(target) },
                )
            }}
        </p>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ProgressBar from '@/components/general/ProgressBar.vue';
import ProgressRing from '@/components/general/ProgressRing.vue';
import type { CambridgePaperId, CambridgeReadiness } from '@/types';
import {
    COLORS,
    PAPER_ICONS,
    PAPERS,
    paperName,
    percent,
    scoreColor,
    verdictInfo,
} from './exam';
import type { VerdictInfo } from './exam';
import type { ExamTag } from './types';

/** "Am I ready?": the verdict, the latest result of every paper and the course done up to the exam's level. */
export default defineComponent({
    name: 'ReadinessCard',

    components: { ProgressBar, ProgressRing },

    props: {
        exam: { type: Object as PropType<ExamTag>, required: true },
        readiness: {
            type: Object as PropType<CambridgeReadiness>,
            required: true,
        },
        /** The safe target of a paper, 0..1. */
        target: { type: Number, required: true },
        /** The "close" score, 0..1. */
        close: { type: Number, required: true },
    },

    data() {
        return {
            papers: PAPERS,
            paperIcons: PAPER_ICONS,
        };
    },

    computed: {
        verdict(): VerdictInfo {
            return verdictInfo(this.readiness.verdict);
        },
    },

    methods: {
        paperName,
        percent,

        latestLabel(paper: CambridgePaperId): string {
            const result = this.readiness.latest[paper];

            return result ? `${percent(result.score)}%` : '—';
        },

        latestColor(paper: CambridgePaperId): string {
            const result = this.readiness.latest[paper];

            return result
                ? scoreColor(result.score, this.target, this.close)
                : COLORS.none;
        },

        latestNote(paper: CambridgePaperId): string {
            const result = this.readiness.latest[paper];

            if (!result) {
                return this.$t('not done yet');
            }

            const rated = paper === 'writing' || paper === 'speaking';

            return (rated ? this.$t('self-assessment · ') : '') + result.date;
        },
    },
});
</script>
