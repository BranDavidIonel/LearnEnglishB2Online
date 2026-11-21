<template>
    <div class="card words-card" :style="{ '--c': words.level.color }">
        <div class="stat-icon">📚</div>
        <div class="grow">
            <h3>
                {{
                    $t('Words for :level: :learned / :total', {
                        level: words.current.name,
                        learned: words.goal.learned,
                        total: words.goal.total,
                    })
                }}
            </h3>
            <p class="muted">
                {{
                    $t(
                        ':left left to go through at :level. :batch a day gets you to the real vocabulary of the level.',
                        {
                            left: words.left,
                            level: words.level.name,
                            batch: words.batch,
                        },
                    )
                }}
            </p>
            <ProgressBar
                :value="
                    words.goal.total ? words.goal.learned / words.goal.total : 0
                "
                :color="words.current.color"
            />
        </div>
        <Link class="btn primary" :href="sortUrl">
            {{
                $t('▶ Learn :n words', {
                    n: Math.min(words.batch, words.left),
                })
            }}
        </Link>
    </div>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ProgressBar from '@/components/general/ProgressBar.vue';
import type { WordsToLearn } from '@/components/modules/home/types';
import wordRoutes from '@/routes/words';

/** "Learn 20 words": the next batch of words to sort, measured against the vocabulary of the current level. */
export default defineComponent({
    name: 'WordsCard',

    components: { Link, ProgressBar },

    props: {
        words: { type: Object as PropType<WordsToLearn>, required: true },
    },

    computed: {
        sortUrl(): string {
            return wordRoutes.sort(this.words.level.id).url;
        },
    },
});
</script>
