<template>
    <div class="row wrap">
        <button
            type="button"
            class="btn"
            :class="{ rec: recording !== null }"
            @click="toggle"
        >
            {{ buttonText }}
        </button>
        <audio controls :class="{ hidden: !url }" :src="url || undefined" />
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { micError } from '@/utils/recognition';
import type { Recording } from '@/utils/recording';
import { startRecording } from '@/utils/recording';
import { toast } from '@/utils/toast';

/** "Record yourself and listen back": works in every browser with a microphone, no speech service needed. */
export default defineComponent({
    name: 'Recorder',

    props: {
        /** Text of the button before the first recording. */
        label: { type: String, default: '' },
    },

    data() {
        return {
            recording: null as Recording | null,
            // Waiting for the microphone (the browser may be asking for permission).
            starting: false,
            // Object URL of the last recording.
            url: '',
            alive: true,
        };
    },

    computed: {
        buttonText(): string {
            if (this.recording) {
                return this.$t('⏹ Stop recording');
            }

            if (this.url) {
                return this.$t('🎙 Record again');
            }

            return this.label || this.$t('🎙 Record yourself');
        },
    },

    beforeUnmount() {
        this.alive = false;
        this.recording?.cancel();
        this.release();
    },

    methods: {
        async toggle(): Promise<void> {
            if (this.recording) {
                this.recording.stop();

                return;
            }

            if (this.starting) {
                return;
            }

            this.starting = true;

            try {
                const recording = await startRecording(this.show);

                if (this.alive) {
                    this.recording = recording;
                } else {
                    recording.cancel();
                }
            } catch {
                toast(micError('not-allowed').text, 'warn');
            } finally {
                this.starting = false;
            }
        },

        show(url: string): void {
            this.recording = null;
            this.release();
            this.url = url;
        },

        release(): void {
            if (this.url) {
                URL.revokeObjectURL(this.url);
                this.url = '';
            }
        },
    },
});
</script>
