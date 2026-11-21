<template>
    <button
        type="button"
        class="btn audio"
        :class="{ playing, 'muted-btn': plays >= 2 && !playing }"
        @click="$emit('toggle')"
    >
        {{
            playing
                ? $t('⏹ Stop')
                : $t('▶ :label (plays: :plays/2)', { label, plays })
        }}
    </button>
</template>

<script lang="ts">
import { defineComponent } from 'vue';

/**
 * Plays a listening recording. In the exam each recording is heard twice: after two plays
 * the button fades, but it still plays, as in the original. The paper decides what plays.
 */
export default defineComponent({
    name: 'AudioButton',

    props: {
        label: { type: String, required: true },
        /** How many times it was started on this part. */
        plays: { type: Number, required: true },
        playing: { type: Boolean, required: true },
    },

    emits: ['toggle'],
});
</script>
