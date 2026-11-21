<template>
    <div v-if="toasts.length" class="toasts">
        <div
            v-for="item in toasts"
            :key="item.id"
            class="toast"
            :class="[item.kind, { out: item.out }]"
        >
            {{ item.message }}
        </div>
    </div>
</template>

<script lang="ts">
import { router } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import type { Toast } from '@/utils/toast';
import { toast, toastState } from '@/utils/toast';

type Flash = { success?: string; error?: string } | undefined;

/** Draws the toasts of toast() and turns the flash messages of the controllers (Inertia::flash) into toasts. */
export default defineComponent({
    name: 'ToastStack',

    data() {
        return {
            stopListening: null as null | (() => void),
        };
    },

    computed: {
        toasts(): Toast[] {
            return toastState.toasts;
        },
    },

    mounted() {
        this.show(this.$page.flash as Flash);
        this.stopListening = router.on('flash', (event) =>
            this.show(event.detail.flash as Flash),
        );
    },

    beforeUnmount() {
        this.stopListening?.();
    },

    methods: {
        show(flash: Flash): void {
            if (flash?.success) {
                this.push(flash.success, '');
            }

            if (flash?.error) {
                this.push(flash.error, 'warn');
            }
        },

        push(message: string, kind: string): void {
            // The same message can arrive twice (the first page and its flash event).
            if (this.toasts.some((item) => item.message === message)) {
                return;
            }

            toast(message, kind);
        },
    },
});
</script>
