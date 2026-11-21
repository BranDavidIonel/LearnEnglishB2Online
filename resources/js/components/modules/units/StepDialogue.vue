<template>
    <h2 class="center">💬 {{ dialogue.titleRo }}</h2>
    <p class="muted center">
        {{
            canListen
                ? $t(
                      'Listen, then tap 🎤 and repeat each line out loud. Tap 🎤 again when you have finished speaking.',
                  )
                : $t('Listen and repeat each line out loud.')
        }}
    </p>
    <div class="row center wrap">
        <button type="button" class="btn primary" @click="togglePlay">
            {{ playing ? $t('⏹ Stop') : $t('▶ Listen to the whole dialogue') }}
        </button>
        <button type="button" class="btn" @click="showRo = !showRo">
            {{ showRo ? $t('🙈 Hide translation') : $t('👁 Show translation') }}
        </button>
        <Link
            v-if="canListen"
            class="btn"
            :href="speaking({ mode: 'role', arg: unit.id }).url"
        >
            {{ $t('🎭 Play a role') }}
        </Link>
    </div>

    <div class="dialogue" :class="{ 'hide-ro': !showRo }">
        <div
            v-for="(line, i) in dialogue.lines"
            :key="i"
            ref="rows"
            class="line"
            :class="[sides[i], { playing: playingLine === i }]"
        >
            <div class="bubble">
                <div class="spk">{{ line.speaker }}</div>
                <TapText class="en" tag="div" :text="line.en" />
                <div class="ro">{{ line.ro }}</div>
                <div class="row line-tools">
                    <SpeakButton :text="line.en" />
                    <button
                        v-if="canListen"
                        type="button"
                        class="icon-btn"
                        :class="{ rec: mics[i].on }"
                        :title="$t('Say the sentence yourself')"
                        @click="say(i)"
                    >
                        🎤
                    </button>
                    <span class="mic-level" :class="{ hidden: !mics[i].on }">
                        <i :style="{ width: `${mics[i].level}%` }" />
                    </span>
                </div>
                <div class="mic-score" :class="mics[i].kind || undefined">
                    {{ mics[i].text }}
                </div>
            </div>
        </div>
    </div>

    <div>
        <div v-if="recorderReason" class="card">
            <h3>{{ $t('🎙 Record yourself and listen back') }}</h3>
            <p class="muted">{{ recorderReason }}</p>
            <Recorder />
        </div>
    </div>

    <StepDoneBar
        :unit-id="unit.id"
        step="dialogue"
        :label="$t('Done ✓ — next: Exercises →')"
    />
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import TapText from '@/components/content/TapText.vue';
import Recorder from '@/components/speech/Recorder.vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import { speaking } from '@/routes';
import type { Dialogue } from '@/types';
import type { Listening } from '@/utils/recognition';
import { canListen, isMicError, listen, MIC_FATAL } from '@/utils/recognition';
import { canRecord } from '@/utils/recording';
import { sfx } from '@/utils/sound';
import { speak, stopSpeak } from '@/utils/speech';
import { speechScore } from '@/utils/text';
import { wordOpen } from '@/utils/wordLookup';
import StepDoneBar from './StepDoneBar.vue';
import type { UnitHead } from './types';

/** What the microphone of one line shows. */
interface MicState {
    /** Listening now. */
    on: boolean;
    /** Loudness bar, 0..100. */
    level: number;
    text: string;
    kind: '' | 'good' | 'warn' | 'bad';
}

// A line said this well counts as right.
const GOOD_SCORE = 0.8;
const CLOSE_SCORE = 0.5;
// Pause before listening, so the speakers go quiet first; pause between lines when playing all.
const MIC_DELAY = 250;
const LINE_PAUSE = 350;

/**
 * Step 3: the unit's dialogue. Listen to all of it or line by line, hide the translation,
 * and say each line yourself: the microphone scores how much of it was understood. Without
 * speech recognition, "record yourself and listen back" takes its place.
 */
export default defineComponent({
    name: 'StepDialogue',

    components: { Link, SpeakButton, Recorder, StepDoneBar, TapText },

    props: {
        unit: { type: Object as PropType<UnitHead>, required: true },
        dialogue: { type: Object as PropType<Dialogue>, required: true },
    },

    data() {
        return {
            canListen: canListen(),
            showRo: true,
            playing: false,
            playingLine: null as number | null,
            // Changes whenever playback is started or stopped, so an old run stops by itself.
            playRun: 0,
            playTimer: undefined as ReturnType<typeof setTimeout> | undefined,
            mics: this.dialogue.lines.map((): MicState => ({
                on: false,
                level: 0,
                text: '',
                kind: '',
            })),
            // The microphone session in progress, if any.
            active: null as { line: number; session: Listening } | null,
            recorderReason: '',
            alive: true,
        };
    },

    computed: {
        wordOpen,

        /** The first speaker on the left, the others on the right. */
        sides(): string[] {
            const first = this.dialogue.lines[0]?.speaker;

            return this.dialogue.lines.map((line) =>
                line.speaker === first ? 'left' : 'right',
            );
        },
    },

    watch: {
        // A word tapped while the dialogue plays: the playing stops (the layout has stopped the voice).
        wordOpen(open: boolean): void {
            if (open && this.playing) {
                this.stopPlay();
            }
        },
    },

    mounted() {
        if (!this.canListen) {
            this.showRecorder(
                this.$t(
                    "This browser doesn't have automatic speech recognition (it works in Chrome and Edge), but you can record yourself and listen back.",
                ),
            );
        }
    },

    beforeUnmount() {
        this.alive = false;
        this.active?.session.stop();
        this.stopPlay();
    },

    methods: {
        speaking,

        showRecorder(reason: string): void {
            if (!this.recorderReason && canRecord()) {
                this.recorderReason = reason;
            }
        },

        stopPlay(): void {
            this.playRun++;
            this.playing = false;
            this.playingLine = null;
            clearTimeout(this.playTimer);
            stopSpeak();
        },

        togglePlay(): void {
            if (this.playing) {
                this.stopPlay();

                return;
            }

            this.active?.session.stop();
            this.playRun++;
            this.playing = true;
            this.playLine(this.playRun, 0);
        },

        playLine(run: number, i: number): void {
            if (run !== this.playRun) {
                return;
            }

            if (i >= this.dialogue.lines.length) {
                this.stopPlay();

                return;
            }

            this.playingLine = i;
            const rows = this.$refs.rows as HTMLElement[] | undefined;
            rows?.[i]?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            speak(this.dialogue.lines[i].en, {
                onend: () => {
                    this.playTimer = setTimeout(
                        () => this.playLine(run, i + 1),
                        LINE_PAUSE,
                    );
                },
            });
        },

        async say(i: number): Promise<void> {
            const mic = this.mics[i];

            // A second tap on the same line = "I've finished speaking".
            if (this.active?.line === i) {
                this.active.session.stop();

                return;
            }

            if (mic.on) {
                return;
            }

            this.active?.session.stop();
            this.stopPlay();
            mic.on = true;
            mic.level = 0;
            mic.kind = '';
            mic.text = this.$t('Starting the microphone…');
            await new Promise((resolve) => setTimeout(resolve, MIC_DELAY));

            if (!this.alive) {
                return;
            }

            const mine = {
                line: i,
                session: listen((kind, value) => this.update(mic, kind, value)),
            };
            this.active = mine;

            try {
                this.score(
                    mic,
                    this.dialogue.lines[i].en,
                    await mine.session.done,
                );
            } catch (error) {
                this.failed(mic, error);
            }

            if (this.active === mine) {
                this.active = null;
            }

            mic.on = false;
        },

        update(mic: MicState, kind: string, value: string | number): void {
            if (kind === 'ready') {
                mic.text = this.$t('🎙 Speak now…');
            } else if (kind === 'info') {
                mic.text = String(value);
            } else if (kind === 'retry') {
                mic.text = this.$t(
                    '🎙 Switched to on-device recognition. Say the sentence again, now…',
                );
            } else if (kind === 'level') {
                mic.level = Math.min(100, Math.round(Number(value) * 250));
            } else if (kind === 'interim') {
                mic.text = this.$t('I hear: “:text”', { text: String(value) });
            }
        },

        score(mic: MicState, target: string, heard: string[]): void {
            const score = speechScore(target, heard);
            const pct = Math.round(score * 100);

            if (score >= GOOD_SCORE) {
                mic.kind = 'good';
                mic.text = this.$t('Excellent! :pct% 🎉', { pct });
                sfx('ok');
            } else {
                mic.kind = 'warn';
                mic.text =
                    score >= CLOSE_SCORE
                        ? this.$t('Almost: :pct% — I heard “:text”', {
                              pct,
                              text: heard[0],
                          })
                        : this.$t('Try again — I heard “:text”', {
                              text: heard[0],
                          });
            }
        },

        failed(mic: MicState, error: unknown): void {
            const code = isMicError(error)
                ? error.code
                : error instanceof Error
                  ? error.message
                  : String(error);
            const text = isMicError(error) && error.text ? error.text : '';

            mic.kind = 'bad';
            mic.text = this.$t(':message (code: :code)', {
                message:
                    text || this.$t('Speech recognition ran into an error.'),
                code,
            });

            if (MIC_FATAL.includes(code)) {
                this.showRecorder(
                    this.$t(
                        "Automatic speech recognition doesn't work in this browser, but you can record yourself, listen back and compare with the app's voice.",
                    ),
                );
            }
        },
    },
});
</script>
