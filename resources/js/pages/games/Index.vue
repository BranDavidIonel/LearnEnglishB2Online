<template>
    <Head :title="$t('Games')" />
    <PageHeader
        :title="$t('🎮 Games')"
        :sub="
            $t(
                'Review words while you play. The games use words you\'ve already met.',
            )
        "
        :back="home().url"
    />
    <div class="game-grid">
        <Link class="card game" :href="games('memory').url">
            <div class="game-icon">🧩</div>
            <h3>{{ $t('Pairs') }}</h3>
            <p class="muted">
                {{ $t('Match English–Romanian pairs as fast as you can.') }}
            </p>
            <ChipTag v-if="records.memory" kind="ok">
                {{ $t('Record: :n moves', { n: records.memory }) }}
            </ChipTag>
        </Link>
        <Link class="card game" :href="games('sprint').url">
            <div class="game-icon">⚡</div>
            <h3>Sprint 60</h3>
            <p class="muted">
                {{
                    $t(
                        'How many words can you translate correctly in 60 seconds?',
                    )
                }}
            </p>
            <ChipTag v-if="records.sprint" kind="ok">
                {{ $t('Record: :n points', { n: records.sprint }) }}
            </ChipTag>
        </Link>
        <Link class="card game" :href="games('spell').url">
            <div class="game-icon">🎧</div>
            <h3>{{ $t('Dictation') }}</h3>
            <p class="muted">
                {{
                    $t(
                        'Listen to the word and spell it correctly, letter by letter.',
                    )
                }}
            </p>
            <ChipTag v-if="records.spell" kind="ok">
                {{ $t('Record: :n/10', { n: records.spell }) }}
            </ChipTag>
        </Link>
    </div>
</template>

<script lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ChipTag from '@/components/general/ChipTag.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { games, home } from '@/routes';
import type { GameId } from '@/types';

/** The word games with the learner's records: Pairs, Sprint 60, Dictation. */
export default defineComponent({
    name: 'GamesIndex',

    components: { ChipTag, Head, Link, PageHeader },

    props: {
        /** Best result of each game played: moves at Pairs, points at Sprint 60, words right at Dictation. */
        records: {
            type: Object as PropType<Partial<Record<GameId, number>>>,
            required: true,
        },
    },

    methods: {
        games,
        home,
    },
});
</script>
