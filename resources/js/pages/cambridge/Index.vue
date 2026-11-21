<template>
    <Head :title="$t('Cambridge exams')" />

    <PageHeader
        :title="$t('📝 Cambridge exams')"
        :sub="
            $t(
                'Mock tests in the real exam format, so you know if you are ready before you sign up.',
            )
        "
        :back="home().url"
    />

    <EmptyState
        v-if="!exams.length"
        icon="⏳"
        :title="$t('Mock tests are on the way')"
        :text="
            $t(
                'The PET (B1) and FCE (B2) tests will appear here as soon as they are ready. Reload the page later.',
            )
        "
    />

    <template v-else>
        <Link
            v-for="exam in exams"
            :key="exam.id"
            class="card exam-card"
            :href="cambridge({ exam: exam.id }).url"
        >
            <ProgressRing
                :value="exam.overall ?? 0"
                :color="examColor"
                :label="exam.level"
                :size="84"
            />
            <div class="grow">
                <h2>{{ exam.name }}</h2>
                <p class="muted">
                    {{
                        $t('1 full mock test|:count full mock tests', {
                            count: exam.tests,
                        })
                    }}
                    · Reading, Listening, Writing, Speaking
                </p>
                <ChipTag :kind="verdictInfo(exam.verdict).kind">
                    {{ verdictInfo(exam.verdict).label }}
                </ChipTag>
            </div>
            <span class="btn primary">{{ $t('Open →') }}</span>
        </Link>

        <div class="card">
            <h3>{{ $t('ℹ️ How to use the mock tests') }}</h3>
            <ul>
                <li>
                    {{
                        $t(
                            "Only take them once you've finished (or nearly finished) that level of the course: PET after B1, FCE after B2.",
                        )
                    }}
                </li>
                <li>
                    {{
                        $t(
                            "Stick to the time shown and don't use a dictionary — just like in the exam.",
                        )
                    }}
                </li>
                <li>
                    {{
                        $t(
                            'Reading and Listening are marked automatically. For Writing and Speaking you compare with a model answer and do a self-assessment.',
                        )
                    }}
                </li>
                <li>
                    {{
                        $t(
                            'The Listening recordings are read by the computer voice. In the real exam the voices are natural and faster, so practise with the videos in the app too.',
                        )
                    }}
                </li>
            </ul>
        </div>
    </template>
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ChipTag from '@/components/general/ChipTag.vue';
import EmptyState from '@/components/general/EmptyState.vue';
import ProgressRing from '@/components/general/ProgressRing.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { COLORS, verdictInfo } from '@/components/modules/cambridge/exam';
import { reloadOnHistory } from '@/components/modules/cambridge/freshProps';
import type { ExamSummary } from '@/components/modules/cambridge/types';
import { cambridge, home } from '@/routes';

/** The Cambridge exams (B1 Preliminary, B2 First), each with the verdict on the learner. */
export default defineComponent({
    name: 'CambridgeIndexPage',

    components: {
        Head,
        Link,
        PageHeader,
        EmptyState,
        ProgressRing,
        ChipTag,
    },

    props: {
        exams: { type: Array as PropType<ExamSummary[]>, required: true },
    },

    data() {
        return {
            examColor: COLORS.exam,
            stopReloading: null as (() => void) | null,
        };
    },

    created() {
        // Back to this page from a paper: show the results saved meanwhile.
        this.stopReloading = reloadOnHistory('cambridge/Index', ['exams']);
    },

    beforeUnmount() {
        this.stopReloading?.();
    },

    methods: {
        cambridge,
        home,
        verdictInfo,
    },
});
</script>
