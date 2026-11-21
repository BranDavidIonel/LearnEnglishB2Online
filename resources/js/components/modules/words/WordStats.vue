<template>
    <div class="card">
        <div class="row between">
            <span>{{ $t('Learned') }}</span>
            <strong>{{ counts.learned }} / {{ counts.total }}</strong>
        </div>
        <ProgressBar
            :value="counts.total ? counts.learned / counts.total : 0"
            :color="color"
        />
        <p class="muted small">
            {{
                $t(':learning being learned · :new to learn', {
                    learning: counts.learning,
                    new: counts.new,
                })
            }}
        </p>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ProgressBar from '@/components/general/ProgressBar.vue';
import type { WordCounts } from './types';

/** How many words of a list are learned, with a bar in the level colour. */
export default defineComponent({
    name: 'WordStats',

    components: { ProgressBar },

    props: {
        counts: { type: Object as PropType<WordCounts>, required: true },
        color: { type: String, required: true },
    },
});
</script>
