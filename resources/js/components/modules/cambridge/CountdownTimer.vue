<template>
    <div
        class="timer"
        :class="{ low: left <= 300 && left > 0, over: left <= 0 }"
    >
        {{ text }}
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { toast } from '@/utils/toast';

/**
 * The time left for a paper. It keeps counting past zero (shown as "+m:ss"), as the
 * original did: the learner may carry on, but is told once that time is up.
 * It runs while it is on the page.
 */
export default defineComponent({
    name: 'CountdownTimer',

    props: {
        minutes: { type: Number, required: true },
    },

    data() {
        return {
            left: this.minutes * 60,
            warned: false,
            timer: 0,
        };
    },

    computed: {
        text(): string {
            const seconds = Math.abs(this.left);
            const sign = this.left < 0 ? '+' : '';

            return `${sign}${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        },
    },

    mounted() {
        this.timer = window.setInterval(this.tick, 1000);
    },

    beforeUnmount() {
        window.clearInterval(this.timer);
    },

    methods: {
        tick(): void {
            this.left--;

            if (this.left <= 0 && !this.warned) {
                this.warned = true;
                toast(
                    this.$t(
                        "⏰ Time's up! In the real exam you would hand in now. You can carry on, but make a note of it.",
                    ),
                    'warn',
                );
            }
        },
    },
});
</script>
