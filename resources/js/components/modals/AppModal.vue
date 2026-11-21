<template>
    <Teleport to="body">
        <div v-if="open" class="modal-back" @click.self="close">
            <div
                class="modal"
                :class="{ wide }"
                role="dialog"
                aria-modal="true"
            >
                <button
                    type="button"
                    class="icon-btn modal-x"
                    :title="$t('Close')"
                    :aria-label="$t('Close')"
                    @click="close"
                >
                    ✕
                </button>
                <slot />
            </div>
        </div>
    </Teleport>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { stopSpeak } from '@/utils/speech';

/** The original modal: ✕ in the corner, closes on a click outside and on Esc, and stops any speech. */
export default defineComponent({
    name: 'AppModal',

    props: {
        open: { type: Boolean, default: false },
        /** 820px instead of 480px (videos, long explanations). */
        wide: { type: Boolean, default: false },
    },

    emits: ['close'],

    mounted() {
        document.addEventListener('keydown', this.onKeydown);
    },

    beforeUnmount() {
        document.removeEventListener('keydown', this.onKeydown);
    },

    methods: {
        close(): void {
            stopSpeak();
            this.$emit('close');
        },

        onKeydown(event: KeyboardEvent): void {
            if (this.open && event.key === 'Escape') {
                this.close();
            }
        },
    },
});
</script>
