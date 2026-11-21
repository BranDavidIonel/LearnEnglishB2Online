<template>
    <Head :title="$t('Answer the question')" />
    <LevelGate :level="level" :open="open">
        <QuestionRound
            v-if="questions"
            :key="run"
            :items="picked"
            :title="
                $t('🙋 Answer the question · level :level', {
                    level: levelName,
                })
            "
            :back="speaking().url"
            @again="newRound"
        />
        <template v-else>
            <PageHeader
                :title="$t('🙋 Answer the question')"
                :back="speaking().url"
            />
            <NoContent
                :title="$t('Questions for this level are coming soon')"
            />
        </template>
    </LevelGate>
</template>

<script lang="ts">
import { Head } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import LevelGate from '@/components/modules/speaking/LevelGate.vue';
import NoContent from '@/components/modules/speaking/NoContent.vue';
import QuestionRound from '@/components/modules/speaking/QuestionRound.vue';
import { speaking } from '@/routes';
import type { SpeakingQuestion } from '@/types';
import { levelInfo } from '@/utils/levels';
import { sample } from '@/utils/random';

// Questions in a round.
const ROUND = 5;

/** Open questions about the learner, answered aloud, five at a time. */
export default defineComponent({
    name: 'SpeakingAnswer',

    components: { Head, LevelGate, NoContent, PageHeader, QuestionRound },

    props: {
        level: { type: String, required: true },
        open: { type: Array as PropType<string[]>, required: true },
        /** The questions of the level, null when it has none. */
        questions: {
            type: Array as PropType<SpeakingQuestion[] | null>,
            default: null,
        },
    },

    data() {
        return {
            run: 0,
            picked: [] as SpeakingQuestion[],
        };
    },

    computed: {
        levelName(): string {
            return levelInfo(this.level)?.name ?? this.level.toUpperCase();
        },
    },

    created() {
        this.pick();
    },

    methods: {
        speaking,

        pick(): void {
            this.picked = sample(this.questions ?? [], ROUND);
        },

        newRound(): void {
            this.pick();
            this.run++;
        },
    },
});
</script>
