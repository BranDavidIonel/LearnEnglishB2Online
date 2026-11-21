<template>
    <div class="card">
        <h3>{{ $t('⚡ Shortcuts') }}</h3>
        <div class="quick">
            <Link class="quick-item" :href="links.speaking">
                🗣️<span>{{ $t('Speaking practice') }}</span>
            </Link>
            <Link class="quick-item" :href="links.verbs">
                🔁<span>{{
                    $t('Irregular verbs (:learned/:total)', {
                        learned: verbCounts.learned,
                        total: verbCounts.total,
                    })
                }}</span>
            </Link>
            <Link class="quick-item" :href="links.games">
                🎮<span>{{ $t('Word games') }}</span>
            </Link>
            <Link class="quick-item" :href="links.mistakes">
                🩹<span>{{
                    mistakeCount
                        ? $t('My mistakes (:n)', { n: mistakeCount })
                        : $t('My mistakes')
                }}</span>
            </Link>
            <Link class="quick-item" :href="links.videos">
                🎬<span>{{ $t('Videos') }}</span>
            </Link>
            <Link class="quick-item" :href="links.cambridge">
                📝<span>{{ $t('Cambridge practice tests') }}</span>
            </Link>
            <Link class="quick-item" :href="links.grammar">
                📐<span>{{ $t('All grammar') }}</span>
            </Link>
            <Link class="quick-item" :href="links.placement">
                🧭<span>{{
                    placementTaken
                        ? $t('Retake the placement test')
                        : $t('Placement test')
                }}</span>
            </Link>
        </div>
    </div>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { ReviewCounts } from '@/components/modules/home/types';
import {
    cambridge,
    games,
    grammar,
    mistakes,
    placement,
    speaking,
    verbs,
    videos,
} from '@/routes';

/** The shortcuts of the home page: speaking, verbs, games, mistakes, videos, Cambridge, grammar, placement. */
export default defineComponent({
    name: 'ShortcutsCard',

    components: { Link },

    props: {
        verbCounts: { type: Object as PropType<ReviewCounts>, required: true },
        /** Questions in "my mistakes". */
        mistakeCount: { type: Number, required: true },
        placementTaken: { type: Boolean, required: true },
    },

    computed: {
        links(): Record<string, string> {
            return {
                speaking: speaking().url,
                verbs: verbs().url,
                games: games().url,
                mistakes: mistakes().url,
                videos: videos().url,
                cambridge: cambridge().url,
                grammar: grammar().url,
                placement: placement().url,
            };
        },
    },
});
</script>
