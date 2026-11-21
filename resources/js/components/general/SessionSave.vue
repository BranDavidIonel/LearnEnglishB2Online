<template>
    <slot v-if="state === 'saved'" />
    <SaveAlert
        v-else-if="failed"
        :busy="state === 'sending'"
        :keeps-answers="keepsAnswers"
        @retry="send"
    />
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { SaveQueue } from '@/utils/saving';
import SaveAlert from './SaveAlert.vue';

/**
 * The end of a session: sends what the session still has to save, then shows its result (the
 * default slot) and emits `saved`. When a request fails it says so and offers to send the rest
 * again, so nothing the learner did is lost. Give it a new :key for every new session.
 */
export default defineComponent({
    name: 'SessionSave',

    components: { SaveAlert },

    props: {
        save: { type: Object as PropType<SaveQueue>, required: true },
        keepsAnswers: { type: Boolean, default: true },
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
