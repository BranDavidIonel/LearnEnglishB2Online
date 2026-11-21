<template>
    <slot v-if="state === 'saved'" />
    <div v-else-if="failed" class="alert warn">
        <p>
            {{
                $t(
                    'Your result could not be saved. Check the connection and try again — your answers are kept.',
                )
            }}
        </p>
        <button
            type="button"
            class="btn primary"
            :disabled="state === 'sending'"
            @click="send"
        >
            {{ $t('🔁 Try again') }}
        </button>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { SaveQueue } from '@/utils/speaking';

/**
 * The end of a speaking round: sends what the round still has to save, then shows the result
 * (the default slot) and emits `saved`. When a request fails it says so and offers to send the
 * rest again, so nothing the learner said is lost.
 */
export default defineComponent({
    name: 'RoundSave',

    props: {
        save: { type: Object as PropType<SaveQueue>, required: true },
    },

    emits: ['saved'],

    data() {
        return {
            state: 'sending' as 'sending' | 'failed' | 'saved',
            // A try failed: the alert stays up while the next try is on its way.
            failed: false,
            alive: true,
        };
    },

    mounted() {
        void this.send();
    },

    beforeUnmount() {
        this.alive = false;
    },

    methods: {
        async send(): Promise<void> {
            this.state = 'sending';
            const saved = await this.save.flush();

            if (!this.alive) {
                return;
            }

            this.failed = !saved;
            this.state = saved ? 'saved' : 'failed';

            if (saved) {
                this.$emit('saved');
            }
        },
    },
});
</script>
