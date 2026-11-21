<template>
    <Head :title="exam.name" />

    <PageHeader
        :title="`📝 ${exam.name}`"
        :sub="$t('Level :level', { level: exam.level })"
        :back="cambridge().url"
    />
    <ReadinessCard
        :exam="exam"
        :readiness="readiness"
        :target="target"
        :close="close"
    />

    <div v-for="test in tests" :key="test.id" class="card">
        <h3>{{ testTitle(test.title) }}</h3>
        <div class="paper-grid">
            <Link
                v-for="paper in test.papers"
                :key="paper.id"
                class="paper"
                :href="
                    cambridge({ exam: exam.id, test: test.id, paper: paper.id })
                        .url
                "
            >
                <div class="paper-icon">{{ paperIcons[paper.id] }}</div>
                <strong>{{ paperName(exam.id, paper.id) }}</strong>
                <small class="muted">{{ paperSize(paper) }}</small>
                <ChipTag :kind="resultChip(test, paper).kind">
                    {{ resultChip(test, paper).text }}
                </ChipTag>
            </Link>
        </div>
    </div>

    <div class="card">
        <h3>{{ $t('About the real exam') }}</h3>
        <p v-html="md(exam.aboutRo)" />
        <div class="gram-list">
            <div
                v-for="item in exam.papersRo"
                :key="item.name"
                class="gram-row"
            >
                <strong>{{ item.name }}</strong>
                <span class="muted grow">{{ item.info }}</span>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ChipTag from '@/components/general/ChipTag.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import {
    PAPER_ICONS,
    paperName,
    percent,
    scoreKind,
    testTitle,
} from '@/components/modules/cambridge/exam';
import type { ScoreKind } from '@/components/modules/cambridge/exam';
import { reloadOnHistory } from '@/components/modules/cambridge/freshProps';
import ReadinessCard from '@/components/modules/cambridge/ReadinessCard.vue';
import type {
    ExamDetails,
    PaperSize,
    TestSummary,
} from '@/components/modules/cambridge/types';
import { cambridge } from '@/routes';
import type { CambridgeReadiness } from '@/types';
import { md } from '@/utils/text';

/** One Cambridge exam: am I ready, its mock tests with the four papers of each, and what the real exam is like. */
export default defineComponent({
    name: 'CambridgeExamPage',

    components: { Head, Link, PageHeader, ChipTag, ReadinessCard },

    props: {
        exam: { type: Object as PropType<ExamDetails>, required: true },
        readiness: {
            type: Object as PropType<CambridgeReadiness>,
            required: true,
        },
        tests: { type: Array as PropType<TestSummary[]>, required: true },
        /** The safe target of a paper, 0..1. */
        target: { type: Number, required: true },
        /** The "close" score, 0..1. */
        close: { type: Number, required: true },
    },

    data() {
        return {
            paperIcons: PAPER_ICONS,
            stopReloading: null as (() => void) | null,
        };
    },

    created() {
        // Back to this page from a paper: show the results saved meanwhile.
        this.stopReloading = reloadOnHistory('cambridge/Exam', [
            'readiness',
            'tests',
        ]);
    },

    beforeUnmount() {
        this.stopReloading?.();
    },

    methods: {
        cambridge,
        md,
        paperName,
        percent,
        testTitle,

        /** The latest result of a paper of a test, or "not started". */
        resultChip(
            test: TestSummary,
            paper: PaperSize,
        ): { kind: ScoreKind | 'lock'; text: string } {
            const result = test.results[paper.id];

            if (!result) {
                return { kind: 'lock', text: this.$t('Not started') };
            }

            return {
                kind: scoreKind(result.score, this.target, this.close),
                text: `${percent(result.score)}% · ${result.date}`,
            };
        },

        paperSize(paper: PaperSize): string {
            const count =
                paper.id === 'reading' || paper.id === 'listening'
                    ? this.$t(':count questions', { count: paper.count })
                    : this.$t(':count tasks', { count: paper.count });

            return `${paper.minutes} min · ${count}`;
        },
    },
});
</script>
