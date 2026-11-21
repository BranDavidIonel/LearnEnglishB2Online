<template>
    <SessionShell
        :title="conversation.title"
        :done="progress"
        :total="learnerTurns"
        :back="back"
    >
        <div v-if="conversation.intro" class="card instr">
            {{ conversation.intro }}
        </div>
        <div class="dialogue talk-log">
            <div
                v-for="(bubble, i) in log"
                :key="i"
                class="line"
                :class="bubble.mine ? 'right' : 'left'"
            >
                <div class="bubble">
                    <div class="spk">{{ bubble.who }}</div>
                    <div class="en">{{ bubble.en }}</div>
                    <div v-if="bubble.ro" class="ro">{{ bubble.ro }}</div>
                    <div v-if="!bubble.mine" class="row line-tools">
                        <SpeakButton :text="bubble.en" />
                    </div>
                    <div v-else-if="bubble.note" class="muted small">
                        {{ bubble.note }}
                    </div>
                </div>
            </div>
        </div>
        <SessionSave v-if="result" :save="saves" @saved="celebrate">
            <ResultCard
                :score="result.score"
                :title="resultTitle"
                :lines="[resultLine]"
            >
                <template #actions>
                    <button
                        type="button"
                        class="btn primary big"
                        @click="$emit('again')"
                    >
                        {{ $t('🔁 Once more') }}
                    </button>
                    <Link class="btn" :href="back">{{ $t('Back') }}</Link>
                </template>
            </ResultCard>
        </SessionSave>
        <div v-else ref="panel" class="turn-panel card">
            <p v-if="partnerSpeaking" class="muted center">
                {{
                    $t(':name is speaking… listen.', {
                        name: conversation.partner,
                    })
                }}
            </p>
            <template v-else-if="turn">
                <div class="turn-head">
                    {{ $t('🎯 Your turn')
                    }}<span class="muted"> · {{ conversation.me }}</span>
                </div>
                <div v-if="turn.show" class="say-target">
                    <SaidWords v-if="words" :words="words" />
                    <template v-else>{{ turn.answers[0] }}</template>
                </div>
                <div class="hint-box">
                    {{ turn.show ? '🇷🇴 ' : '💡 ' }}{{ turn.hint }}
                </div>
                <div>
                    <div v-if="modelShown" class="example">
                        <SpeakButton :text="turn.answers[0]" />
                        <div>
                            <div class="en">{{ turn.answers[0] }}</div>
                            <div v-if="turn.answers.length > 1" class="ro">
                                {{
                                    $t('or: :answers', {
                                        answers: turn.answers
                                            .slice(1)
                                            .join(' / '),
                                    })
                                }}
                            </div>
                        </div>
                    </div>
                </div>
                <MicPanel :key="turnNumber" @heard="onHeard" />
                <div>
                    <div v-if="missed !== null" class="feedback bad">
                        <div class="fb-head">
                            {{ $t('Not yet — try again') }}
                        </div>
                        <div>
                            {{ $t('I heard:') }} <em>„{{ missed }}”</em>
                        </div>
                    </div>
                </div>
                <div class="row center wrap">
                    <button
                        v-if="turn.show"
                        type="button"
                        class="btn"
                        @click="hearLine"
                    >
                        {{ $t('🔊 Hear the line') }}
                    </button>
                    <button v-else type="button" class="btn" @click="showModel">
                        {{ $t('👁 Show an answer') }}
                    </button>
                    <button type="button" class="btn" @click="skip">
                        {{ $t('Skip →') }}
                    </button>
                </div>
            </template>
        </div>
    </SessionShell>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { countSpoken, recordConversation } from '@/api/progress';
import ResultCard from '@/components/exercise/ResultCard.vue';
import SessionSave from '@/components/general/SessionSave.vue';
import MicPanel from '@/components/modules/speaking/MicPanel.vue';
import SaidWords from '@/components/modules/speaking/SaidWords.vue';
import SessionShell from '@/components/modules/speaking/SessionShell.vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import { confetti } from '@/utils/confetti';
import { saveQueue } from '@/utils/saving';
import { sfx } from '@/utils/sound';
import type { Conversation, LearnerTurn } from '@/utils/speaking';
import { averageScore, judgeTurn, sayAsync } from '@/utils/speaking';
import { speak, stopSpeak } from '@/utils/speech';
import type { SpokenWord } from '@/utils/text';

interface Bubble {
    mine: boolean;
    who: string;
    en: string;
    ro?: string;
    /** Under the learner's line: the model answer, or "skipped". */
    note?: string | null;
}

// The pause after the learner's line before the conversation goes on.
const NEXT_DELAY = 650;

/**
 * A conversation in turns: the app says the partner's lines aloud, the learner answers each of
 * theirs into the microphone. At the end the score (the average of the learner's lines) is saved.
 * Emits `again` for "Once more".
 */
export default defineComponent({
    name: 'ConversationRun',

    components: {
        Link,
        MicPanel,
        ResultCard,
        SessionSave,
        SaidWords,
        SessionShell,
        SpeakButton,
    },

    props: {
        conversation: {
            type: Object as PropType<Conversation>,
            required: true,
        },
        /** Where ✕ and "Back" lead. */
        back: { type: String, required: true },
    },

    emits: ['again'],

    data() {
        return {
            alive: true,
            // The next turn to play.
            next: 0,
            scores: [] as number[],
            // Lines said, as the progress bar shows them (updated when the next turn starts).
            progress: 0,
            log: [] as Bubble[],
            partnerSpeaking: false,
            turn: null as LearnerTurn | null,
            // Changes with every learner turn, so each one gets a fresh microphone.
            turnNumber: 0,
            tries: 0,
            helped: false,
            answered: false,
            words: null as SpokenWord[] | null,
            modelShown: false,
            // What was heard when the line was not good enough.
            missed: null as string | null,
            timer: undefined as ReturnType<typeof setTimeout> | undefined,
            // The result, once the conversation is over (shown when it is saved).
            result: null as { score: number; said: number; xp: number } | null,
            saves: saveQueue(),
        };
    },

    computed: {
        learnerTurns(): number {
            return this.conversation.turns.filter((t) => t.who === 'me').length;
        },

        resultTitle(): string {
            const score = this.result?.score ?? 0;

            if (score >= 0.85) {
                return this.$t(
                    'Excellent! You handled it like a real conversation 🎉',
                );
            }

            return score >= 0.6
                ? this.$t('Good! The conversation worked.')
                : this.$t('You got through it — do it once more.');
        },

        resultLine(): string {
            return this.$t(
                '1 of :total lines said · +:xp XP ⚡|:count of :total lines said · +:xp XP ⚡',
                {
                    count: this.result?.said ?? 0,
                    total: this.learnerTurns,
                    xp: this.result?.xp ?? 0,
                },
            );
        },
    },

    mounted() {
        void this.play();
    },

    beforeUnmount() {
        this.alive = false;
        clearTimeout(this.timer);
        stopSpeak();
    },

    methods: {
        /** Plays the next turn: the partner's lines one after another, then the learner's. */
        async play(): Promise<void> {
            if (!this.alive) {
                return;
            }

            this.progress = this.scores.length;
            const turn = this.conversation.turns[this.next];

            if (!turn) {
                this.finish();

                return;
            }

            this.next++;

            if (turn.who === 'partner') {
                this.turn = null;
                this.partnerSpeaking = true;
                this.log.push({
                    mine: false,
                    who: this.conversation.partner,
                    en: turn.en,
                    ro: turn.ro,
                });
                this.scrollToPanel();
                await sayAsync(turn.en);
                await this.play();

                return;
            }

            this.partnerSpeaking = false;
            this.turn = turn;
            this.turnNumber++;
            this.tries = 0;
            this.helped = false;
            this.answered = false;
            this.words = null;
            this.modelShown = false;
            this.missed = null;
            this.scrollToPanel();
        },

        onHeard(heard: string[] | null): void {
            const turn = this.turn;

            if (!turn || this.answered) {
                return;
            }

            this.tries++;

            if (heard === null) {
                this.saySpoken();
                this.finishTurn(0.7, turn.answers[0]);

                return;
            }

            const verdict = judgeTurn(turn, heard);

            if (turn.show) {
                this.words = verdict.words;
            }

            if (verdict.pass) {
                sfx('ok');
                this.saySpoken();
                this.finishTurn(
                    this.helped || this.tries > 1 ? 0.7 : 1,
                    verdict.heard,
                    turn.show
                        ? null
                        : this.$t('✓ Model: :answer', {
                              answer: verdict.answer,
                          }),
                );

                return;
            }

            sfx('bad');
            this.missed = verdict.heard;

            if (this.tries >= 2 && !turn.show) {
                this.showModel();
            }
        },

        showModel(): void {
            this.helped = true;
            this.modelShown = true;
        },

        hearLine(): void {
            if (this.turn) {
                speak(this.turn.answers[0]);
            }
        },

        skip(): void {
            if (this.turn) {
                this.finishTurn(0, this.turn.answers[0], this.$t('skipped'));
            }
        },

        /** The learner's line is over: it joins the dialogue and the conversation goes on. */
        finishTurn(
            score: number,
            said: string,
            note: string | null = null,
        ): void {
            if (this.answered) {
                return;
            }

            this.answered = true;
            this.scores.push(score);
            this.log.push({
                mine: true,
                who: this.conversation.me,
                en: said,
                note,
            });
            this.timer = setTimeout(() => void this.play(), NEXT_DELAY);
        },

        saySpoken(): void {
            this.saves.add(() => countSpoken());
        },

        finish(): void {
            const score = averageScore(this.scores);
            this.turn = null;
            this.partnerSpeaking = false;
            this.result = {
                score,
                said: this.scores.filter((s) => s > 0).length,
                xp: 0,
            };
            this.saves.add(async () => {
                const answer = await recordConversation(
                    this.conversation.id,
                    score,
                );

                if (this.result) {
                    this.result.xp = answer.xp;
                }
            });
        },

        /** The result is saved: celebrate a good conversation. */
        celebrate(): void {
            if ((this.result?.score ?? 0) >= 0.7) {
                confetti();
                sfx('win');
            }
        },

        scrollToPanel(): void {
            void this.$nextTick(() => {
                const panel = this.$refs.panel as HTMLElement | undefined;
                panel?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
        },
    },
});
</script>
