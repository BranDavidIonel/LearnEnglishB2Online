<template>
    <SessionShell
        :title="$t('👂 Sound-alike words')"
        :done="progress"
        :total="items.length"
        :back="back"
    >
        <SessionSave v-if="result" :save="saves" @saved="celebrate">
            <ResultCard
                :score="good / items.length"
                :title="
                    result.record && good
                        ? $t('New record! 🏆')
                        : $t('Round finished')
                "
                :lines="resultLines"
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
        </SessionSave>
        <div v-else-if="item" :key="index" class="ex fade-in">
            <div class="ex-type">
                {{ $t('Listen to both words, then say the one asked for') }}
            </div>
            <div class="pair-cards">
                <button
                    v-for="word in item.pair"
                    :key="word"
                    type="button"
                    class="pair-card"
                    :class="{ target: word === item.target }"
                    @click="say(word)"
                >
                    <span>🔊</span><strong>{{ word }}</strong>
                </button>
            </div>
            <div class="ex-q center">
                {{ $t('Say:') }} <span class="tw hit">{{ item.target }}</span>
            </div>
            <MicPanel @heard="onHeard" />
            <div>
                <div
                    v-if="feedback"
                    class="feedback"
                    :class="feedback.good ? 'good' : 'bad'"
                >
                    <div class="fb-head">{{ feedback.head }}</div>
                    <div v-if="!feedback.good">
                        {{ $t('I heard:') }} <em>„{{ feedback.heard }}”</em>
                        {{ $t('· listen to the difference and try again.') }}
                    </div>
                </div>
            </div>
            <div class="row center mt">
                <button type="button" class="btn" @click="nextPair">
                    {{ $t('Skip →') }}
                </button>
            </div>
        </div>
    </SessionShell>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import { addXp, speakRecord } from '@/api/progress';
import ResultCard from '@/components/exercise/ResultCard.vue';
import SessionSave from '@/components/general/SessionSave.vue';
import MicPanel from '@/components/modules/speaking/MicPanel.vue';
import SessionShell from '@/components/modules/speaking/SessionShell.vue';
import { confetti } from '@/utils/confetti';
import { sample } from '@/utils/random';
import { saveQueue } from '@/utils/saving';
import { sfx } from '@/utils/sound';
import { saidWord, SOUND_ALIKE_PAIRS } from '@/utils/speaking';
import { speak, stopSpeak } from '@/utils/speech';

interface PairItem {
    pair: [string, string];
    target: string;
}

const ROUND = 8;

/**
 * Sound-alike words (ship / sheep): hear both, say the one asked for. A word said right at the
 * first try counts; the round's result is kept as a record, 2 XP for each word. Emits `again`.
 */
export default defineComponent({
    name: 'PairsRound',

    components: { Link, MicPanel, ResultCard, SessionSave, SessionShell },

    props: {
        /** Where ✕ and "Back" lead. */
        back: { type: String, required: true },
    },

    emits: ['again'],

    data() {
        return {
            alive: true,
            items: sample(SOUND_ALIKE_PAIRS, ROUND).map((pair): PairItem => ({
                pair,
                target: pair[Math.floor(Math.random() * 2)],
            })),
            index: 0,
            progress: 0,
            good: 0,
            tries: 0,
            feedback: null as {
                good: boolean;
                head: string;
                heard: string;
            } | null,
            // The end of the round (shown when it is saved).
            result: null as { record: boolean } | null,
            saves: saveQueue(),
            timer: undefined as ReturnType<typeof setTimeout> | undefined,
        };
    },

    computed: {
        item(): PairItem | null {
            return this.items[this.index] ?? null;
        },

        resultLines(): string[] {
            return [
                this.$t(':good of :total · +:xp XP ⚡', {
                    good: this.good,
                    total: this.items.length,
                    xp: this.good * 2,
                }),
                this.$t(
                    'Speech recognition sometimes gets single words wrong. If you are sure you said it right, listen to the model again and compare.',
                ),
            ];
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
            const item = this.item;

            if (!item) {
                this.finish();

                return;
            }

            this.progress = this.index;
            this.tries = 0;
            this.feedback = null;
            speak(item.target);
        },

        say(word: string): void {
            speak(word);
        },

        onHeard(heard: string[] | null): void {
            const item = this.item;

            // Said right already: the next pair is coming.
            if (!item || this.feedback?.good) {
                return;
            }

            this.tries++;

            if (heard === null || heard.some((x) => saidWord(x, item.target))) {
                sfx('ok');

                if (this.tries === 1) {
                    this.good++;
                }

                this.feedback = {
                    good: true,
                    head: this.$t('Yes — “:word” ✅', { word: item.target }),
                    heard: '',
                };

                const at = this.index;
                this.timer = setTimeout(() => {
                    if (this.alive && this.index === at) {
                        this.nextPair();
                    }
                }, 1000);

                return;
            }

            const other = item.pair.find((w) => w !== item.target) ?? '';

            sfx('bad');
            this.feedback = {
                good: false,
                head: heard.some((x) => saidWord(x, other))
                    ? this.$t('I heard “:word” — the other word', {
                          word: other,
                      })
                    : this.$t('I did not catch the word'),
                heard: heard[0] ?? '',
            };
        },

        nextPair(): void {
            this.index++;
            this.show();
        },

        finish(): void {
            const good = this.good;
            this.result = { record: false };
            this.saves.add(async () => {
                const answer = await speakRecord('pairs', good);

                if (this.result) {
                    this.result.record = answer.record;
                }
            });

            if (good) {
                this.saves.add(() => addXp(good * 2));
            }
        },

        /** The round is saved: celebrate a good one. */
        celebrate(): void {
            if (this.good >= 6) {
                confetti(1500);
                sfx('win');
            }
        },
    },
});
</script>
