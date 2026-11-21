<template>
    <div class="alert warn">
        <p>
            {{
                keepsAnswers
                    ? $t(
                          'Your result could not be saved. Check the connection and try again — your answers are kept.',
                      )
                    : $t(
                          'Your result could not be saved. Check the connection and try again.',
                      )
            }}
        </p>
        <button
            type="button"
            class="btn primary"
            :disabled="busy"
            @click="$emit('retry')"
        >
            {{ $t('🔁 Try again') }}
        </button>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';

/** "Your result could not be saved" with a button that sends it again. */
export default defineComponent({
    name: 'SaveAlert',

    props: {
        /** A try is on its way: the button waits for it. */
        busy: { type: Boolean, default: false },
        /** The learner's answers wait on the page to be sent again (not so for a step only read). */
        keepsAnswers: { type: Boolean, default: true },
    },

    emits: ['retry'],
});
</script>
