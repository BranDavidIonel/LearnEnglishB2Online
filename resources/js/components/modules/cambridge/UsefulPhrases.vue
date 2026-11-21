<template>
    <details v-if="phrases.length" class="script">
        <summary>{{ $t('💬 Useful phrases') }}</summary>
        <div v-for="phrase in phrases" :key="phrase.en" class="example">
            <SpeakButton :text="phrase.en" />
            <div>
                <div class="en">{{ phrase.en }}</div>
                <div class="ro">{{ phrase.ro }}</div>
            </div>
        </div>
    </details>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import type { Example } from '@/types';

/** The useful phrases of a Writing or Speaking task, folded, each with its translation and 🔊. */
export default defineComponent({
    name: 'UsefulPhrases',

    components: { SpeakButton },

    props: {
        phrases: { type: Array as PropType<Example[]>, default: () => [] },
    },
});
</script>
