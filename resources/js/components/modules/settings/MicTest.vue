<template>
    <div class="mt">
        <div class="row wrap">
            <button class="btn" :class="{ rec: !!listening }" @click="toggle">
                {{
                    listening
                        ? $t('⏹ I have finished speaking')
                        : $t('🎙 Test the microphone')
                }}
            </button>
            <span class="muted small">
                {{ $t('Check that the app hears and understands you.') }}
            </span>
        </div>
        <div class="mic-status" :class="outcome">{{ status }}</div>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import type { Listening } from '@/utils/recognition';
import { isMobileDevice } from '@/utils/microphone';
import { isMicError, listen, speechEngine } from '@/utils/recognition';

/**
 * "Test the microphone": listens to one sentence and says what was heard and which recognition
 * engine heard it (online, or on this computer), or why it failed.
 */
export default defineComponent({
    name: 'MicTest',

    data() {
        return {
            alive: true,
            listening: null as Listening | null,
            status: '',
            outcome: '' as '' | 'good' | 'bad',
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
                void this.test();
            }
        },

        async test(): Promise<void> {
            this.outcome = '';
            this.status = this.$t('Starting the microphone…');
            const listening = listen((kind, value) => {
                if (kind === 'ready') {
                    // A phone does not tell which microphone the recogniser uses.
                    this.status = isMobileDevice()
                        ? this.$t('🎙 Listening… say something in English.')
                        : this.$t(
                              '🎙 Say something in English… (microphone: :name)',
                              { name: value || this.$t('unknown') },
                          );
                } else if (kind === 'interim') {
                    this.status = `„${value}”`;
                } else if (kind === 'info') {
                    this.status = value;
                } else if (kind === 'retry') {
                    this.status = this.$t(
                        '🎙 Switched to on-device recognition. Say it again…',
                    );
                }
            });
            this.listening = listening;

            try {
                const heard = await listening.done;

                if (this.alive) {
                    this.outcome = 'good';
                    this.status = this.$t(
                        '✅ It works! I heard: “:text” · :engine',
                        {
                            text: heard[0],
                            engine:
                                speechEngine() === 'local'
                                    ? this.$t('on-device recognition')
                                    : this.$t('online recognition'),
                        },
                    );
                }
            } catch (error) {
                if (this.alive) {
                    this.outcome = 'bad';
                    this.status = this.failure(error);
                }
            }

            this.listening = null;
        },

        failure(error: unknown): string {
            const text =
                isMicError(error) && error.text
                    ? error.text
                    : this.$t('Speech recognition failed.');
            const code = isMicError(error)
                ? error.code
                : error instanceof Error
                  ? error.message
                  : '';

            return `${text} (${this.$t('code')}: ${code})`;
        },
    },
});
</script>
