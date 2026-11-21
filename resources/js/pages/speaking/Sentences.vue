<template>
    <Head
        :title="
            mode === 'say' ? $t('Say it in English') : $t('Repeat after me')
        "
    />
    <LevelGate :level="level" :open="open">
        <template v-if="round">
            <SentenceRound
                v-if="round.items.length"
                :key="run"
                :items="picked"
                :title="title"
                :hidden="mode === 'say'"
                :back="back"
                @again="newRound"
            />
            <EmptyState v-else icon="⏳" :title="$t('Nothing to practise yet')">
                <template #actions>
                    <Link class="btn primary" :href="speaking().url">
                        {{ $t('Back') }}
                    </Link>
                </template>
            </EmptyState>
        </template>
        <template v-else>
            <PageHeader
                :title="$t('🔁 Repeat after me')"
                :sub="
                    $t('Pick a topic (level :level). 8 sentences per round.', {
                        level: levelName,
                    })
                "
                :back="speaking().url"
            />
            <div class="game-grid">
                <Link
                    v-for="(topic, n) in topics"
                    :key="n"
                    class="card game"
                    :href="topicUrl(String(n))"
                >
                    <div class="game-icon">{{ topic.emoji }}</div>
                    <h3>{{ topic.topic }}</h3>
                    <p class="muted">{{ topic.topicRo }}</p>
                </Link>
                <Link
                    v-if="lessons"
                    class="card game"
                    :href="topicUrl('lessons')"
                >
                    <div class="game-icon">📚</div>
                    <h3>{{ $t('From my lessons') }}</h3>
                    <p class="muted">
                        {{ $t('Lines from the dialogues of your open units.') }}
                    </p>
                </Link>
            </div>
            <NoContent
                v-if="!topics.length"
                :title="$t('Phrase topics for this level are coming soon')"
            />
        </template>
    </LevelGate>
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import EmptyState from '@/components/general/EmptyState.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import LevelGate from '@/components/modules/speaking/LevelGate.vue';
import NoContent from '@/components/modules/speaking/NoContent.vue';
import SentenceRound from '@/components/modules/speaking/SentenceRound.vue';
import { speaking } from '@/routes';
import type { Example } from '@/types';
import { levelInfo } from '@/utils/levels';
import { sample } from '@/utils/random';

interface TopicCard {
    emoji: string;
    topic: string;
    topicRo: string;
}

interface Round {
    /** A phrase topic, the lines of the open lessons, or "say it in English". */
    kind: 'topic' | 'lessons' | 'say';
    /** The topic's emoji and name (topic rounds only). */
    title: string | null;
    /** Every sentence the round may use. */
    items: Example[];
}

// Sentences in a round.
const ROUND = 8;

/** Repeat after me (topics, then a round) and say it in English (a round). */
export default defineComponent({
    name: 'SpeakingSentences',

    components: {
        EmptyState,
        Head,
        LevelGate,
        Link,
        NoContent,
        PageHeader,
        SentenceRound,
    },

    props: {
        mode: { type: String as PropType<'repeat' | 'say'>, required: true },
        level: { type: String, required: true },
        open: { type: Array as PropType<string[]>, required: true },
        /** The phrase topics of the level (the topic list only). */
        topics: { type: Array as PropType<TopicCard[]>, default: () => [] },
        /** The level has open lesson dialogues (the topic list only). */
        lessons: { type: Boolean, default: false },
        round: { type: Object as PropType<Round | null>, default: null },
    },

    data() {
        return {
            run: 0,
            picked: [] as Example[],
        };
    },

    computed: {
        levelName(): string {
            return levelInfo(this.level)?.name ?? this.level.toUpperCase();
        },

        title(): string {
            if (this.round?.kind === 'say') {
                return this.$t('🇬🇧 Say it in English · level :level', {
                    level: this.levelName,
                });
            }

            return this.round?.kind === 'lessons'
                ? this.$t('🔁 Sentences from your lessons')
                : (this.round?.title ?? '');
        },

        /** ✕ and "Back" of a round: the topics of "repeat", the hub for "say". */
        back(): string {
            return this.mode === 'say'
                ? speaking().url
                : speaking({ mode: 'repeat' }, { query: { level: this.level } })
                      .url;
        },
    },

    created() {
        this.pick();
    },

    methods: {
        speaking,

        topicUrl(topic: string): string {
            return speaking(
                { mode: 'repeat', arg: topic },
                { query: { level: this.level } },
            ).url;
        },

        pick(): void {
            this.picked = sample(this.round?.items ?? [], ROUND);
        },

        newRound(): void {
            this.pick();
            this.run++;
        },
    },
});
</script>
