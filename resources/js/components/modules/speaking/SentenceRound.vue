<template>
    <SessionShell
        :title="title"
        :done="progress"
        :total="items.length"
        :back="back"
    >
        <RoundSave v-if="finished" :save="saves" @saved="celebrate">
            <ResultCard
                :score="ok / items.length"
                :title="
                    ok / items.length >= 0.75
                        ? $t('Very good! 💪')
                        : $t('Round finished')
                "
                :lines="[resultLine]"
            >
                <template #actions>
                    <button
                        type="button"
                        class="btn primary big"
                        @click="$emit('again')"
                    >
                        {{ $t('🔁 New round') }}
                    </button>
                    <Link class="btn" :href="back">{{ $t('Back') }}</Link>
                </template>
            </ResultCard>
        </RoundSave>
        <div v-else-if="item" :key="index" class="ex fade-in">
            <div class="ex-type">
                {{ hidden ? $t('Say it in English') : $t('Listen and repeat') }}
            </div>
            <div v-if="hidden" class="ex-q">🇷🇴 {{ item.ro }}</div>
            <div class="say-target">
                <template v-if="revealed">
                    <SaidWords v-if="words" :words="words" />
                    <template v-else>{{ item.en }}</template>
                </template>
                <span v-else class="muted">•••</span>
            </div>
            <div v-if="!hidden" class="muted center">{{ item.ro }}</div>
            <MicPanel ref="mic" @heard="onHeard" />
            <div>
                <div
                    v-if="verdict"
                    class="feedback"
                    :class="verdict.pass ? 'good' : 'bad'"
                >
                    <div class="fb-head">{{ verdictHead }}</div>
                    <div v-if="!verdict.pass">
                        {{ $t('I heard:') }} <em>„{{ verdict.heard }}”</em>
                    </div>
                </div>
            </div>
            <div class="row center wrap mt">
                <button type="button" class="btn" @click="showAnswer">
                    {{
                        hidden
                            ? $t('👁 Show and hear the answer')
                            : $t('🔊 Listen again')
                    }}
                </button>
                <button type="button" class="btn" @click="skip">
                    {{ $t('Skip →') }}
                </button>
            </div>
        </div>
    </SessionShell>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { countSpoken } from '@/api/progress';
import ResultCard from '@/components/exercise/ResultCard.vue';
import MicPanel from '@/components/modules/speaking/MicPanel.vue';
import RoundSave from '@/components/modules/speaking/RoundSave.vue';
import SaidWords from '@/components/modules/speaking/SaidWords.vue';
import SessionShell from '@/components/modules/speaking/SessionShell.vue';
import type { Example } from '@/types';
import { confetti } from '@/utils/confetti';
import { sfx } from '@/utils/sound';
import {
    allSaid,
    sayAsync,
    saveQueue,
    sentencePassMark,
} from '@/utils/speaking';
import { speak, stopSpeak } from '@/utils/speech';
import type { SpokenWord } from '@/utils/text';
import { speechDetail } from '@/utils/text';

/**
 * A round of sentences to say: "repeat after me" (the app says the sentence, then listens) or
 * "say it in English" (the learner sees only the Romanian). Each sentence said well enough counts
 * (2 XP). Emits `again` for "New round".
 */
export default defineComponent({
    name: 'SentenceRound',

    components: {
        Link,
        MicPanel,
        ResultCard,
        RoundSave,
        SaidWords,
        SessionShell,
    },

    props: {
        items: { type: Array as PropType<Example[]>, required: true },
        title: { type: String, required: true },
        /** Say it in English: the sentence is hidden until it is said (or shown). */
        hidden: { type: Boolean, default: false },
        /** Where ✕ and "Back" lead. */
        back: { type: String, required: true },
    },

    emits: ['again'],

    data() {
        return {
            alive: true,
            index: 0,
            // The progress bar stays on the last sentence when the round ends.
            progress: 0,
            ok: 0,
            tries: 0,
            revealed: false,
            words: null as SpokenWord[] | null,
            verdict: null as {
                pass: boolean;
                score: number;
                heard: string;
            } | null,
            finished: false,
            // XP of the sentences said, as the server gave it.
            xp: 0,
            saves: saveQueue(),
            timer: undefined as ReturnType<typeof setTimeout> | undefined,
        };
    },

    computed: {
        item(): Example | null {
            return this.items[this.index] ?? null;
        },

        verdictHead(): string {
            const verdict = this.verdict;

            if (!verdict) {
                return '';
            }

            const pct = Math.round(verdict.score * 100);

            if (!verdict.pass) {
                return this.$t(':pct% — try again', { pct });
            }

            return verdict.score === 1
                ? this.$t('Perfect! ⭐')
                : this.$t('Good! :pct% ✅', { pct });
        },

        resultLine(): string {
            return this.$t(
                '1 of :total said well · +:xp XP ⚡|:count of :total said well · +:xp XP ⚡',
                { count: this.ok, total: this.items.length, xp: this.xp },
            );
        },
    },

    mounted() {
        this.show();
    },

    beforeUnmount() {
        this.alive = false;
        clearTimeout(this.timer);
        stopSpeak();
    },

    methods: {
        show(): void {
            if (!this.alive) {
                return;
            }

            const item = this.item;

            if (!item) {
                this.finished = true;

                return;
            }

            const at = this.index;
            this.progress = at;
            this.tries = 0;
            this.revealed = !this.hidden;
            this.words = null;
            this.verdict = null;

            if (!this.hidden) {
                // Listen as soon as the sentence has been said.
                void sayAsync(item.en).then(() => {
                    if (this.alive && this.index === at) {
                        void (
                            this.$refs.mic as
                                | InstanceType<typeof MicPanel>
                                | undefined
                        )?.start();
                    }
                });
            }
        },

        onHeard(heard: string[] | null): void {
            const item = this.item;

            // Said well already: the next sentence is coming.
            if (!item || this.verdict?.pass) {
                return;
            }

            this.tries++;

            const detail =
                heard === null
                    ? { score: 1, heard: '', words: allSaid(item.en) }
                    : speechDetail(item.en, heard);
            const pass = detail.score >= sentencePassMark(this.hidden);

            if (pass || this.tries >= 2) {
                this.revealed = true;
            }

            this.words = detail.words;
            this.verdict = { pass, score: detail.score, heard: detail.heard };

            if (!pass) {
                sfx('bad');

                return;
            }

            sfx('ok');
            this.ok++;
            this.saves.add(async () => {
                this.xp += (await countSpoken()).xp;
            });

            if (this.hidden) {
                speak(item.en);
            }

            const at = this.index;
            this.timer = setTimeout(
                () => {
                    if (this.alive && this.index === at) {
                        this.skip();
                    }
                },
                this.hidden ? 1900 : 1100,
            );
        },

        showAnswer(): void {
            if (!this.item) {
                return;
            }

            this.revealed = true;
            this.words = null;
            speak(this.item.en);
        },

        skip(): void {
            this.index++;
            this.show();
        },

        /** The round is saved: celebrate a good one. */
        celebrate(): void {
            if (this.ok / this.items.length >= 0.75) {
                confetti(1500);
                sfx('win');
            }
        },
    },
});
</script>
