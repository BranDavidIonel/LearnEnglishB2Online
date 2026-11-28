<template>
    <div class="to-goal">
        <div class="row between">
            <span>{{ $t('📚 Words for :level', { level: level.name }) }}</span>
            <strong>{{ stats.learned }} / {{ stats.total }}</strong>
        </div>
        <ProgressBar
            :value="stats.total ? stats.learned / stats.total : 0"
            :color="level.color"
        />
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ProgressBar from '@/components/general/ProgressBar.vue';

/** "📚 Words for B1: 340 / 3150": the vocabulary goal of a level, counting the levels before it. */
export default defineComponent({
    name: 'VocabGoal',

    components: { ProgressBar },

    props: {
        stats: {
            type: Object as PropType<{ learned: number; total: number }>,
            required: true,
        },
        level: {
            type: Object as PropType<{ name: string; color: string }>,
            required: true,
        },
    },
});
</script>
