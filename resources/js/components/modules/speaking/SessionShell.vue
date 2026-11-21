<template>
    <div class="session">
        <div class="sess-top">
            <button
                type="button"
                class="icon-btn"
                :title="$t('Exit')"
                @click="exit"
            >
                ✕
            </button>
            <div class="bar">
                <div class="bar-fill" :style="{ width: `${share}%` }" />
            </div>
            <span class="sess-count">{{ counter }}</span>
        </div>
        <div class="sess-title">{{ title }}</div>
        <div>
            <slot />
        </div>
    </div>
</template>

<script lang="ts">
import { router } from '@inertiajs/vue3';
import { defineComponent } from 'vue';

/** The frame of a speaking round: ✕ (back to `back`), progress bar, "3 / 8" and the title. */
export default defineComponent({
    name: 'SessionShell',

    props: {
        title: { type: String, required: true },
        /** Items finished so far. */
        done: { type: Number, required: true },
        total: { type: Number, required: true },
        /** Where ✕ leads. */
        back: { type: String, required: true },
    },

    computed: {
        share(): number {
            return this.total ? (this.done / this.total) * 100 : 0;
        },

        counter(): string {
            return `${Math.min(this.done + 1, this.total)} / ${this.total}`;
        },
    },

    methods: {
        exit(): void {
            router.visit(this.back);
        },
    },
});
</script>
