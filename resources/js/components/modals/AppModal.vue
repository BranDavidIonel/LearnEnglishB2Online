<template>
    <Teleport to="body">
        <div
            v-if="open"
            ref="back"
            class="modal-back"
            :class="{ sheet }"
            @click.self="close"
        >
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

/**
 * The original modal: ✕ in the corner, closes on a click outside and on Esc (the top one only,
 * when two are open), and stops any speech.
 */
export default defineComponent({
    name: 'AppModal',

    props: {
        open: { type: Boolean, default: false },
        /** 820px instead of 480px (videos, long explanations). */
        wide: { type: Boolean, default: false },
        /**
         * Near the top of the screen instead of centred (a bottom sheet on phones), so it grows
         * downwards without moving when its content arrives (the word window).
         */
        sheet: { type: Boolean, default: false },
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
            if (this.open && event.key === 'Escape' && this.onTop()) {
                this.close();
            }
        },

        /** Esc closes only the modal opened last (the word window over the word details). */
        onTop(): boolean {
            const all = document.querySelectorAll('.modal-back');

            return all[all.length - 1] === this.$refs.back;
        },
    },
});
</script>
