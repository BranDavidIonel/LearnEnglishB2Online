<template>
    <AppModal open sheet @close="$emit('close')">
        <div ref="box" class="lookup" tabindex="-1" :aria-label="word">
            <div class="lookup-head">
                <span class="lookup-word">{{ word }}</span>
                <button
                    type="button"
                    class="icon-btn speak"
                    :title="$t('Listen')"
                    @click="hear(false)"
                >
                    🔊
                </button>
                <button
                    type="button"
                    class="icon-btn"
                    :title="$t('Slower')"
                    @click="hear(true)"
                >
                    🐢
                </button>
            </div>
            <div class="vipa lookup-ipa">
                <template v-if="ipa">{{ ipa }}</template>
                <em v-if="entry?.pos">{{ ipa ? ' · ' : '' }}{{ entry.pos }}</em>
            </div>

            <div
                v-if="phase === 'loading'"
                class="lookup-wait"
                aria-hidden="true"
            >
                <span class="lookup-bar" />
            </div>
            <p v-else-if="phase === 'failed'" class="muted center lookup-none">
                {{
                    $t(
                        'The meaning could not be loaded. You can still hear the word and say it.',
                    )
                }}
            </p>
            <p v-else-if="!entry" class="muted center lookup-none">
                {{ $t('Not in the course vocabulary') }}
            </p>
            <template v-else>
                <div v-if="lookup?.source === 'name'" class="lookup-note">
                    {{ entry.ro }}
                </div>
                <div v-else class="vro lookup-ro">
                    {{ entry.emoji ? `${entry.emoji} ` : '' }}{{ entry.ro }}
                </div>
                <div v-if="relation" class="lookup-from">
                    {{ word }} — {{ $t(relation) }}
                    <strong>{{ lookup?.base }}</strong>
                </div>
                <div v-if="verb" class="lookup-verb">
                    <span class="muted small">{{ $t('Irregular verb:') }}</span>
                    <strong
                        >{{ verb.v1 }} – {{ verb.v2 }} – {{ verb.v3 }}</strong
                    >
                    <SpeakButton :text="`${verb.v1}, ${verb.v2}, ${verb.v3}`" />
                </div>
                <div
                    v-for="phrase in lookup?.phrases"
                    :key="phrase.en"
                    class="lookup-phrase"
                >
                    <span class="muted small">{{ $t('Expression:') }}</span>
                    <strong>{{ phrase.en }}</strong> — {{ phrase.ro }}
                </div>
            </template>

            <div v-if="phase === 'loading' || state" class="lookup-state">
                <template v-if="state">
                    <div class="row center wrap">
                        <ChipTag :kind="status.kind">{{
                            $t(status.label)
                        }}</ChipTag>
                        <ChipTag
                            v-if="lookup?.source === 'glossary'"
                            :title="
                                $t(
                                    'Extra words do not count in the course totals.',
                                )
                            "
                        >
                            {{ $t('➕ extra word') }}
                        </ChipTag>
                        <span v-if="state.known" class="muted small">
                            {{ $t('· marked as known by you') }}
                        </span>
                    </div>
                    <div class="row center wrap">
                        <button
                            v-if="state.status === 'new'"
                            type="button"
                            class="btn primary"
                            :disabled="saving"
                            @click="change(learnWord)"
                        >
                            {{ $t('🧠 Learn it') }}
                        </button>
                        <button
                            v-if="state.status !== 'learned'"
                            type="button"
                            class="btn success"
                            :disabled="saving"
                            @click="change(markKnown)"
                        >
                            {{ $t('✓ I already know it') }}
                        </button>
                        <button
                            v-if="state.status === 'learned'"
                            type="button"
                            class="btn"
                            :disabled="saving"
                            @click="change(learnWord)"
                        >
                            {{ $t('↺ Practise it again') }}
                        </button>
                        <button
                            v-if="state.status !== 'new'"
                            type="button"
                            class="btn"
                            :disabled="saving"
                            @click="change(forgetWord)"
                        >
                            {{ $t('Remove from flashcards') }}
                        </button>
                    </div>
                </template>
            </div>

            <p v-if="verb && !state" class="muted small center">
                {{
                    $t(
                        'An irregular verb: its three forms have their own practice.',
                    )
                }}
                <Link :href="verbsUrl">{{ $t('Irregular verbs') }}</Link>
            </p>

            <div class="lookup-say">
                <button
                    v-if="!saying"
                    type="button"
                    class="btn"
                    @click="saying = true"
                >
                    {{ $t('🎙 Say it') }}
                </button>
                <template v-else>
                    <MicPanel
                        :self-check-text="
                            $t(
                                'This browser has no speech recognition. Say the word aloud (you can record yourself) and check yourself.',
                            )
                        "
                        @heard="onHeard"
                    />
                    <div
                        v-if="said"
                        class="feedback"
                        :class="said.good ? 'good' : 'bad'"
                    >
                        <div class="fb-head">{{ said.head }}</div>
                        <div v-if="!said.good && said.heard">
                            {{ $t('I heard:') }} <em>„{{ said.heard }}”</em>
                        </div>
                    </div>
                </template>
            </div>

            <div v-if="showSentence" class="example">
                <SpeakButton :text="sentence" />
                <TapText class="en" :text="sentence" />
            </div>
            <div v-if="entry?.ex && entry.ex !== sentence" class="example">
                <SpeakButton :text="entry.ex" />
                <div>
                    <TapText class="en" tag="div" :text="entry.ex" />
                    <div v-if="entry.exRo" class="ro">{{ entry.exRo }}</div>
                </div>
            </div>

            <p v-if="unitUrl" class="muted small center">
                {{ $t('From:') }}
                <Link :href="unitUrl">{{ unitLabel }}</Link>
            </p>
        </div>
    </AppModal>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { forgetWord, learnWord, lookupWord, markKnown } from '@/api/progress';
import TapText from '@/components/content/TapText.vue';
import ChipTag from '@/components/general/ChipTag.vue';
import AppModal from '@/components/modals/AppModal.vue';
import MicPanel from '@/components/speech/MicPanel.vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import { verbs } from '@/routes';
import { show } from '@/routes/units';
import type { WordLookup, WordsAnswer } from '@/types';
import { levelInfo } from '@/utils/levels';
import { sfx } from '@/utils/sound';
import { saidWord } from '@/utils/speaking';
import { speak } from '@/utils/speech';
import { toast } from '@/utils/toast';
import { RELATIONS, unitOrder } from './lookup';
import { STATUS } from './status';

type Entry = NonNullable<WordLookup['entry']>;
type LookupState = NonNullable<WordLookup['state']>;
type Verb = NonNullable<WordLookup['verb']>;

/** What the learner's "say it" gave. */
interface Said {
    good: boolean;
    head: string;
    heard: string;
}

/**
 * The word window: a word tapped anywhere in the lessons. It opens at once with the word and
 * its sound (speech needs no server) and fills in when the lookup answers: meaning, the form
 * it comes from, the verb forms, the expressions of the sentence, the learner's state with the
 * actions of the word details. "Say it" checks the word with the microphone. A failed lookup
 * still leaves hearing and saying. Emits "changed" with the words whose state changed.
 */
export default defineComponent({
    name: 'WordLookup',

    components: { AppModal, ChipTag, Link, MicPanel, SpeakButton, TapText },

    props: {
        /** The word as tapped. */
        word: { type: String, required: true },
        /** The sentence it was tapped in. */
        sentence: { type: String, default: '' },
    },

    emits: ['close', 'changed'],

    data() {
        return {
            phase: 'loading' as 'loading' | 'done' | 'failed',
            lookup: null as WordLookup | null,
            // The learner's state of the headword, kept fresh by the actions.
            state: null as LookupState | null,
            saving: false,
            saying: false,
            said: null as Said | null,
            alive: true,
        };
    },

    computed: {
        entry(): Entry | null {
            return this.lookup?.entry ?? null;
        },

        verb(): Verb | null {
            return this.lookup?.verb ?? null;
        },

        ipa(): string {
            return this.entry?.ipa || this.verb?.ipa || '';
        },

        relation(): string {
            const relation = this.lookup?.relation;

            return relation && this.lookup?.base ? RELATIONS[relation] : '';
        },

        status(): { label: string; kind: string } {
            return STATUS[this.state?.status ?? 'new'];
        },

        /** The sentence is worth showing when it is more than the word itself. */
        showSentence(): boolean {
            return this.sentence.trim() !== '' && this.sentence !== this.word;
        },

        verbsUrl(): string {
            return verbs().url;
        },

        unitUrl(): string {
            return this.entry?.unit ? show(this.entry.unit).url : '';
        },

        unitLabel(): string {
            const unit = this.entry?.unit ?? '';
            const level = levelInfo(this.entry?.levelId ?? '');

            return this.$t(':level · Unit :order', {
                level: level?.name ?? '',
                order: unitOrder(unit),
            });
        },
    },

    mounted() {
        (this.$refs.box as HTMLElement | undefined)?.focus({
            preventScroll: true,
        });
        this.hear(false);
        void this.load();
    },

    beforeUnmount() {
        this.alive = false;
    },

    methods: {
        markKnown,
        learnWord,
        forgetWord,

        hear(slow: boolean): void {
            speak(this.word, { slow });
        },

        async load(): Promise<void> {
            try {
                // The sentence tells the server "May I…?" from "in May" and finds the expressions.
                const { lookup } = await lookupWord(
                    this.word,
                    this.sentence.trim() || undefined,
                );

                if (!this.alive) {
                    return;
                }

                this.lookup = lookup;
                this.state = lookup.state;
                this.phase = 'done';
            } catch {
                if (this.alive) {
                    this.phase = 'failed';
                }
            }
        },

        /** Saves a change of the headword's state; the window changes with the server's answer. */
        async change(
            save: (word: string) => Promise<WordsAnswer>,
        ): Promise<void> {
            const headword = this.entry?.en;

            if (!headword || this.saving) {
                return;
            }

            this.saving = true;

            try {
                const answer = await save(headword);
                const fresh = answer.words.find((w) => w.en === headword);

                if (fresh && this.alive) {
                    this.state = {
                        status: fresh.status,
                        box: fresh.box ?? 0,
                        due: fresh.due,
                        known: fresh.known,
                    };
                }

                if (save === learnWord && fresh?.status === 'learning') {
                    toast(this.$t('Added to your flashcards'));
                }

                this.$emit('changed', answer.words);
            } catch {
                // send() has told the learner; the window stays as it was.
            } finally {
                this.saving = false;
            }
        },

        /** The same check as the speaking area gives a single word. */
        onHeard(heard: string[] | null): void {
            if (heard === null || heard.some((x) => saidWord(x, this.word))) {
                sfx('ok');
                this.said = {
                    good: true,
                    head: this.$t('Yes — “:word” ✅', { word: this.word }),
                    heard: '',
                };

                return;
            }

            sfx('bad');
            this.said = {
                good: false,
                head: this.$t('I did not catch the word'),
                heard: heard[0] ?? '',
            };
        },
    },
});
</script>
