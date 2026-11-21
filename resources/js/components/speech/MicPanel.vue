<template>
    <div v-if="!recognition" class="mic-panel">
        <p class="muted small">
            {{
                selfCheckText ||
                $t(
                    'This browser has no speech recognition. Say the sentence aloud (you can record yourself) and check yourself.',
                )
            }}
        </p>
        <Recorder v-if="recordable" />
        <div class="row center mt">
            <button type="button" class="btn success" @click="selfCheck">
                {{ $t('✓ I said it well') }}
            </button>
        </div>
    </div>
    <div v-else class="mic-panel">
        <div class="row center">
            <button
                type="button"
                class="btn round mic-big"
                :class="{ rec: listening !== null }"
                :title="$t('Press and speak')"
                @click="toggle"
            >
                🎤
            </button>
            <span class="mic-level" :class="{ hidden: listening === null }">
                <i :style="{ width: `${loudness}%` }" />
            </span>
        </div>
        <div class="mic-status" :class="{ bad: failed }">{{ status }}</div>
        <div class="center">
            <Recorder v-if="offerRecorder" />
        </div>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import Recorder from '@/components/speech/Recorder.vue';
import type { Listening } from '@/utils/recognition';
import { canListen, isMicError, listen, MIC_FATAL } from '@/utils/recognition';
import { canRecord } from '@/utils/recording';
import { stopSpeak } from '@/utils/speech';
import { wordOpen } from '@/utils/wordLookup';

/**
 * The microphone of a speaking exercise or of the word window: a big button, a loudness meter
 * and a status line. Emits `heard` with the alternatives understood, or with null when the
 * browser cannot recognise speech and the learner says they said it well. The parent may call
 * start() to listen without a click (after the app has said the sentence); it waits while the
 * word window is open, so two microphones never listen at once.
 */
export default defineComponent({
    name: 'MicPanel',

    components: { Recorder },

    props: {
        /** What to do without speech recognition (default: about a sentence). */
        selfCheckText: { type: String, default: '' },
    },

    emits: {
        heard: (alternatives: string[] | null) =>
            alternatives === null || Array.isArray(alternatives),
    },

    data() {
        return {
            recognition: canListen(),
            recordable: canRecord(),
            listening: null as Listening | null,
            // Loudness in percent of the meter.
            loudness: 0,
            status: '',
            failed: false,
            // After an error that trying again will not fix: let the learner record themself.
            offerRecorder: false,
            alive: true,
        };
    },

    beforeUnmount() {
        this.alive = false;
        this.listening?.stop();
    },

    methods: {
        toggle(): void {
            if (this.listening) {
                this.listening.stop();
            } else {
                void this.listenNow();
            }
        },

        /** Listens without a click, unless the word window is open over the page. */
        async start(): Promise<void> {
            if (!wordOpen()) {
                await this.listenNow();
            }
        },

        /** Listens to one sentence (does nothing without speech recognition or while listening). */
        async listenNow(): Promise<void> {
            if (!this.recognition || !this.alive || this.listening) {
                return;
            }

            stopSpeak();
            this.failed = false;
            this.status = this.$t('Starting the microphone…');

            const listening = listen((kind, value) => {
                if (!this.alive) {
                    return;
                }

                if (kind === 'ready') {
                    this.status = this.$t(
                        '🎙 Speak now… (press again when you finish)',
                    );
                } else if (kind === 'level') {
                    this.loudness = Math.min(100, Math.round(value * 250));
                } else if (kind === 'interim') {
                    this.status = `„${value}”`;
                } else if (kind === 'info') {
                    this.status = value;
                } else {
                    this.status = this.$t(
                        '🎙 Switched to on-device recognition. Say it again now…',
                    );
                }
            });
            this.listening = listening;

            let heard: string[] | null = null;
            let error: unknown = null;

            try {
                heard = await listening.done;
            } catch (e) {
                error = e;
            }

            this.listening = null;

            if (!this.alive) {
                return;
            }

            if (heard === null) {
                this.showError(error);

                return;
            }

            this.status = '';
            this.$emit('heard', heard);
        },

        showError(error: unknown): void {
            const known = isMicError(error);
            const code = known
                ? error.code
                : error instanceof Error
                  ? error.message
                  : String(error);

            this.failed = true;
            this.status = this.$t(':text (code: :code)', {
                text:
                    (known && error.text) ||
                    this.$t('Speech recognition failed.'),
                code,
            });

            if (MIC_FATAL.includes(code) && this.recordable) {
                this.offerRecorder = true;
            }
        },

        selfCheck(): void {
            this.$emit('heard', null);
        },
    },
});
</script>
