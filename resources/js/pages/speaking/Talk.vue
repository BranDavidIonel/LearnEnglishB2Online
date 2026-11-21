<template>
    <Head :title="$t('Conversations')" />
    <ConversationRun
        v-if="played"
        :key="run"
        :conversation="played"
        :back="speaking({ mode: 'talk' }).url"
        @again="run++"
    />
    <LevelGate v-else-if="level" :level="level" :open="open">
        <PageHeader
            :title="$t('🤝 Conversations')"
            :sub="
                $t(
                    'Real situations at level :level. Read what you have to do, listen and answer aloud.',
                    { level: levelName },
                )
            "
            :back="speaking().url"
        />
        <NoContent
            v-if="!scenarios"
            :title="$t('Conversations for this level are coming soon')"
        />
        <div v-else class="units" :style="{ '--c': levelColor }">
            <Link
                v-for="(item, n) in scenarios"
                :key="item.id"
                class="unit-card"
                :href="speaking({ mode: 'talk', arg: item.id }).url"
            >
                <div class="unit-top">
                    <span class="unit-emoji">{{ item.emoji }}</span>
                    <span class="unit-badge">{{ badge(item.score) }}</span>
                </div>
                <div class="unit-n">
                    {{ $t('Conversation :n', { n: n + 1 }) }}
                </div>
                <div class="unit-title">{{ item.title }}</div>
                <div class="unit-ro">{{ item.titleRo }}</div>
                <div class="unit-gram">
                    {{ $t(':n lines to say', { n: item.lines })
                    }}{{
                        item.score === null
                            ? ''
                            : ` · ${Math.round(item.score * 100)}%`
                    }}
                </div>
            </Link>
        </div>
    </LevelGate>
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import ConversationRun from '@/components/modules/speaking/ConversationRun.vue';
import LevelGate from '@/components/modules/speaking/LevelGate.vue';
import NoContent from '@/components/modules/speaking/NoContent.vue';
import { speaking } from '@/routes';
import type { SpeakingScenario } from '@/types';
import { levelInfo } from '@/utils/levels';
import type { Conversation } from '@/utils/speaking';
import { scenarioConversation } from '@/utils/speaking';

interface ScenarioCard {
    id: string;
    emoji: string;
    title: string;
    titleRo: string;
    /** How many lines the learner says. */
    lines: number;
    /** Best score, 0..1, null when never finished. */
    score: number | null;
}

/** Conversations: the list of a level, or one conversation in which the app is the partner. */
export default defineComponent({
    name: 'SpeakingTalk',

    components: {
        ConversationRun,
        Head,
        LevelGate,
        Link,
        NoContent,
        PageHeader,
    },

    props: {
        /** The conversation being played (then the list props are not sent). */
        scenario: {
            type: Object as PropType<SpeakingScenario | null>,
            default: null,
        },
        level: { type: String, default: '' },
        open: { type: Array as PropType<string[]>, default: () => [] },
        /** The conversations of the level, null when it has none. */
        scenarios: {
            type: Array as PropType<ScenarioCard[] | null>,
            default: null,
        },
    },

    data() {
        return {
            // "Once more" starts the conversation again from a fresh runner.
            run: 0,
        };
    },

    computed: {
        played(): Conversation | null {
            return this.scenario
                ? scenarioConversation(this.scenario, this.$t('You'))
                : null;
        },

        levelName(): string {
            return levelInfo(this.level)?.name ?? this.level.toUpperCase();
        },

        levelColor(): string {
            return levelInfo(this.level)?.color ?? 'var(--primary)';
        },
    },

    methods: {
        speaking,

        /** ⭐ from 85%, ✓ when finished. */
        badge(score: number | null): string {
            if (score === null) {
                return '';
            }

            return score >= 0.85 ? '⭐' : '✓';
        },
    },
});
</script>
