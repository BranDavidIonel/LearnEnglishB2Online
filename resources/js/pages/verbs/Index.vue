<template>
    <Head :title="$t('Irregular verbs')" />
    <PageHeader
        :title="$t('📖 My words')"
        :sub="
            $t(
                'Irregular verbs: the three forms you need to know by heart, from the most used to the rarest.',
            )
        "
        :back="home().url"
    />
    <WordTabs :counts="wordCounts" :verbs="verbs.length" active="verbs" />

    <div>
        <EmptyState
            v-if="!verbs.length"
            icon="🔁"
            :title="$t('The verb list is not available yet')"
        />
        <template v-else>
            <div v-show="mode === 'list'">
                <div class="card">
                    <div class="row between">
                        <span>{{ $t('Irregular verbs learned') }}</span>
                        <strong
                            >{{ counts.learned }} / {{ counts.total }}</strong
                        >
                    </div>
                    <ProgressBar
                        :value="
                            counts.total ? counts.learned / counts.total : 0
                        "
                        :color="VERBS_COLOR"
                    />
                    <p class="muted small">
                        {{
                            $t(
                                ':learning being learned · :new to learn · :due to review today',
                                {
                                    learning: counts.learning,
                                    new: counts.new,
                                    due: dueVerbs.length,
                                },
                            )
                        }}
                    </p>
                </div>
                <div class="card verb-help">
                    <div class="verb-help-grid">
                        <div>
                            <strong>V1 · say</strong>
                            <span class="muted small">
                                {{
                                    $t(
                                        'base form: present, future (will say), after did / can / to',
                                    )
                                }}
                            </span>
                        </div>
                        <div>
                            <strong>V2 · said</strong>
                            <span class="muted small">
                                {{
                                    $t(
                                        'past simple: yesterday, last year (affirmative only)',
                                    )
                                }}
                            </span>
                        </div>
                        <div>
                            <strong>V3 · said</strong>
                            <span class="muted small">
                                {{
                                    $t(
                                        'past participle: after have / had / be (I have said, it was said)',
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                    <div class="row center mt">
                        <button
                            type="button"
                            class="btn help-btn"
                            @click.stop="helpOpen = true"
                        >
                            {{ $t('❓ How the three forms are used') }}
                        </button>
                    </div>
                </div>
                <div class="row center wrap">
                    <button
                        v-if="dueVerbs.length"
                        class="btn primary big"
                        @click="review(dueVerbs.slice(0, REVIEW_MAX))"
                    >
                        {{ $t('🔁 Review :n due today', { n: reviewCount }) }}
                    </button>
                    <button
                        v-if="freshVerbs.length"
                        class="btn big"
                        :class="{ primary: !dueVerbs.length }"
                        @click="learn(freshVerbs.slice(0, BATCH))"
                    >
                        {{ $t('▶ Learn the next :n', { n: batchCount }) }}
                    </button>
                    <button
                        v-if="freshVerbs.length"
                        class="btn big"
                        :title="
                            $t(
                                'Type the forms before seeing them: the ones you already know go straight to “learned”',
                            )
                        "
                        @click="checkMe(freshVerbs.slice(0, BATCH))"
                    >
                        {{ $t('⚡ Test me first (:n)', { n: batchCount }) }}
                    </button>
                    <button
                        v-if="
                            counts.learned + counts.learning >=
                            FREE_PRACTICE_MIN
                        "
                        class="btn"
                        @click="freePractice"
                    >
                        {{ $t('🎲 Free practice') }}
                    </button>
                </div>
                <p class="muted small center">
                    {{
                        $t(
                            'The verbs come in order of use: the first ones are the most frequent. Every correct answer earns XP for your daily goal.',
                        )
                    }}
                    <button
                        type="button"
                        class="icon-btn help-icon"
                        :title="$t('How the three forms are used')"
                        @click="helpOpen = true"
                    >
                        ❓
                    </button>
                </p>
                <VerbList
                    :verbs="verbs"
                    @open="details = $event"
                    @learn="learn([$event])"
                />
            </div>

            <VerbStudy
                v-if="mode === 'study'"
                :key="run"
                :verbs="sessionVerbs"
                :title="studyTitle"
                @done="startDrill('learn')"
                @help="helpOpen = true"
            />
            <VerbDrill
                v-else-if="mode === 'drill'"
                :key="run"
                :verbs="sessionVerbs"
                :title="drillTitle"
                @answer="onAnswer"
                @done="onDone"
            />
            <SessionSave
                v-else-if="mode === 'summary' && summary"
                :key="run"
                :save="saves"
                @saved="refresh"
            >
                <VerbSummary
                    :results="summary.results"
                    :title="summary.title"
                    :lines="summary.lines"
                >
                    <template #actions>
                        <button
                            v-if="summary.missed.length"
                            class="btn primary big"
                            @click="learn(summary.missed)"
                        >
                            {{
                                $t('📖 Learn the :n you missed now', {
                                    n: summary.missed.length,
                                })
                            }}
                        </button>
                        <button
                            v-if="dueVerbs.length"
                            class="btn primary big"
                            @click="review(dueVerbs.slice(0, REVIEW_MAX))"
                        >
                            {{
                                $t('🔁 Review :n due today', { n: reviewCount })
                            }}
                        </button>
                        <button
                            v-if="freshVerbs.length"
                            class="btn"
                            :class="{ 'primary big': !dueVerbs.length }"
                            @click="learn(freshVerbs.slice(0, BATCH))"
                        >
                            {{ $t('▶ The next :n verbs', { n: batchCount }) }}
                        </button>
                        <button class="btn" @click="showList">
                            {{ $t('Verb list') }}
                        </button>
                    </template>
                </VerbSummary>
            </SessionSave>
        </template>
    </div>

    <AppModal :open="details !== null" @close="details = null">
        <VerbDetails
            v-if="details"
            :key="details.v1"
            :verb="details"
            @learn="fromDetails(learn)"
            @check="fromDetails(checkMe)"
            @again="changeFromDetails(learnVerb)"
            @forget="changeFromDetails(forgetVerb)"
            @help="helpOpen = true"
        />
    </AppModal>
    <!-- After the modal of a verb, so the explanation opened from it comes on top. -->
    <FormsHelp :open="helpOpen" @close="helpOpen = false" />
</template>

<script lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent, markRaw } from 'vue';
import {
    addXp,
    forgetVerb,
    gradeVerb,
    learnVerb,
    verbKnown,
} from '@/api/progress';
import EmptyState from '@/components/general/EmptyState.vue';
import ProgressBar from '@/components/general/ProgressBar.vue';
import SessionSave from '@/components/general/SessionSave.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import AppModal from '@/components/modals/AppModal.vue';
import FormsHelp from '@/components/modules/verbs/FormsHelp.vue';
import VerbDetails from '@/components/modules/verbs/VerbDetails.vue';
import VerbDrill from '@/components/modules/verbs/VerbDrill.vue';
import VerbList from '@/components/modules/verbs/VerbList.vue';
import type { DrillResult, VerbItem } from '@/components/modules/verbs/verbs';
import {
    BATCH,
    FREE_PRACTICE,
    FREE_PRACTICE_MIN,
    REVIEW_MAX,
    VERBS_COLOR,
} from '@/components/modules/verbs/verbs';
import VerbStudy from '@/components/modules/verbs/VerbStudy.vue';
import VerbSummary from '@/components/modules/verbs/VerbSummary.vue';
import WordTabs from '@/components/modules/words/WordTabs.vue';
import { home } from '@/routes';
import type { ReviewStatus } from '@/types';
import { sample } from '@/utils/random';
import type { SaveQueue } from '@/utils/saving';
import { saveQueue } from '@/utils/saving';
import { stopSpeak } from '@/utils/speech';

type Mode = 'list' | 'study' | 'drill' | 'summary';

/** learn: study then type new verbs · check: type them first · review: the due ones · free: practice, not graded. */
type DrillKind = 'learn' | 'check' | 'review' | 'free';

type Counts = Record<ReviewStatus | 'total', number>;

interface Summary {
    results: DrillResult[];
    title: string;
    lines: string[];
    /** The verbs missed in a "test me first", offered to learn right away. */
    missed: VerbItem[];
}

/**
 * Irregular verbs, a tab of "My words": the three forms (go – went – gone) learned ten at a time
 * (study, then type them), tested first, reviewed with spaced repetition, and the whole list.
 * Each answer is saved at once; the summary shows once everything is saved, and the page then
 * asks the server for the fresh statuses, counts and due verbs.
 */
export default defineComponent({
    name: 'VerbsIndex',

    components: {
        AppModal,
        EmptyState,
        FormsHelp,
        Head,
        PageHeader,
        ProgressBar,
        SessionSave,
        VerbDetails,
        VerbDrill,
        VerbList,
        VerbStudy,
        VerbSummary,
        WordTabs,
    },

    props: {
        /** The numbers of the word tabs. */
        wordCounts: { type: Object as PropType<Counts>, required: true },
        counts: { type: Object as PropType<Counts>, required: true },
        /** Every verb, the most used first. */
        verbs: { type: Array as PropType<VerbItem[]>, required: true },
        /** Base forms of the verbs due today, in review order. */
        due: { type: Array as PropType<string[]>, required: true },
    },

    data() {
        return {
            BATCH,
            FREE_PRACTICE_MIN,
            REVIEW_MAX,
            VERBS_COLOR,
            mode: 'list' as Mode,
            // A new number for every session, so its component starts afresh.
            run: 0,
            sessionVerbs: [] as VerbItem[],
            studyTitle: '',
            drillKind: 'learn' as DrillKind,
            xp: 0,
            summary: null as Summary | null,
            details: null as VerbItem | null,
            helpOpen: false,
            // The saves of the running session; its summary waits for them.
            saves: markRaw(saveQueue()) as SaveQueue,
        };
    },

    computed: {
        byBase(): Map<string, VerbItem> {
            return new Map(this.verbs.map((verb) => [verb.v1, verb]));
        },

        dueVerbs(): VerbItem[] {
            return this.due
                .map((v1) => this.byBase.get(v1))
                .filter((verb): verb is VerbItem => verb !== undefined);
        },

        freshVerbs(): VerbItem[] {
            return this.verbs.filter((verb) => verb.status === 'new');
        },

        reviewCount(): number {
            return Math.min(REVIEW_MAX, this.dueVerbs.length);
        },

        batchCount(): number {
            return Math.min(BATCH, this.freshVerbs.length);
        },

        drillTitle(): string {
            const titles: Record<DrillKind, string> = {
                learn: this.$t('Type the forms from memory'),
                check: this.$t(
                    '⚡ Check: type the forms without seeing them first',
                ),
                review: this.$t("🔁 Review: today's verbs"),
                free: this.$t('🎲 Free practice'),
            };

            return titles[this.drillKind];
        },
    },

    beforeUnmount() {
        stopSpeak();
    },

    methods: {
        home,
        learnVerb,
        forgetVerb,

        /** Starts a session: a new component, the top of the page, nothing being read. */
        start(mode: Mode, verbs: VerbItem[]): void {
            stopSpeak();
            this.sessionVerbs = verbs;
            this.saves = markRaw(saveQueue());
            this.xp = 0;
            this.summary = null;
            this.mode = mode;
            this.run++;
            window.scrollTo(0, 0);
        },

        /** Study the verbs (look, listen), then type them from memory. */
        learn(verbs: VerbItem[]): void {
            this.studyTitle = this.$t('🔁 New verbs: :list', {
                list: verbs.map((verb) => verb.v1).join(', '),
            });
            this.start('study', verbs);
        },

        /** A typing drill; without verbs, on the verbs just studied. */
        startDrill(kind: DrillKind, verbs?: VerbItem[]): void {
            this.drillKind = kind;
            this.start('drill', verbs ?? this.sessionVerbs);
        },

        /** Type first: the verbs already known count as learned. */
        checkMe(verbs: VerbItem[]): void {
            this.startDrill('check', verbs);
        },

        /** The verbs due today (spaced repetition). */
        review(verbs: VerbItem[]): void {
            this.startDrill('review', verbs);
        },

        /** Verbs already met, at random; nothing is graded. */
        freePractice(): void {
            const met = this.verbs.filter((verb) => verb.status !== 'new');
            this.startDrill('free', sample(met, FREE_PRACTICE));
        },

        /** One verb answered (first attempt): what it does to the review queue and the XP. */
        onAnswer(verb: VerbItem, ok: boolean): void {
            const points =
                this.drillKind === 'learn' || this.drillKind === 'check'
                    ? 2
                    : 1;

            if (this.drillKind === 'learn') {
                this.saves.add(() =>
                    ok ? gradeVerb(verb.v1, 2) : learnVerb(verb.v1),
                );
            } else if (this.drillKind === 'check') {
                this.saves.add(() =>
                    ok ? verbKnown(verb.v1) : learnVerb(verb.v1),
                );
            } else if (this.drillKind === 'review') {
                this.saves.add(() => gradeVerb(verb.v1, ok ? 2 : 0));
            }

            if (ok) {
                this.xp += points;
                this.saves.add(() => addXp(points));
            }
        },

        onDone(results: DrillResult[], early: boolean): void {
            if (early && !results.length) {
                this.showList();

                return;
            }

            this.summary = this.summaryOf(results);
            this.mode = 'summary';
            this.run++;
            window.scrollTo(0, 0);
        },

        summaryOf(results: DrillResult[]): Summary {
            const xp = { xp: this.xp };

            if (this.drillKind === 'learn') {
                return {
                    results,
                    title: this.$t('New verbs learned'),
                    lines: [
                        this.$t(
                            'You review them tomorrow, then at growing intervals so they stick. · +:xp XP ⚡',
                            xp,
                        ),
                    ],
                    missed: [],
                };
            }

            if (this.drillKind === 'check') {
                const missed = results.filter((r) => !r.ok).map((r) => r.verb);

                return {
                    results,
                    title: missed.length
                        ? this.$t('Now you know exactly what to learn')
                        : this.$t('You knew them all! 💪'),
                    lines: [
                        this.$t(
                            'The ones you knew get one more check in a week. · +:xp XP ⚡',
                            xp,
                        ),
                    ],
                    missed,
                };
            }

            return {
                results,
                title: this.$t('Review done'),
                lines: [`+${this.xp} XP ⚡`],
                missed: [],
            };
        },

        showList(): void {
            stopSpeak();
            this.mode = 'list';
            this.summary = null;
            this.refresh();
        },

        /** The server sends the fresh statuses, counts and due verbs. */
        refresh(): void {
            router.reload({ only: ['verbs', 'counts', 'due'] });
        },

        /** "Learn it now" / "Test me" in the modal of a verb. */
        fromDetails(action: (verbs: VerbItem[]) => void): void {
            const verb = this.details;
            this.details = null;

            if (verb) {
                action([verb]);
            }
        },

        /** "Practise it again" / "Remove from review" in the modal of a verb. */
        async changeFromDetails(
            save: (v1: string) => Promise<unknown>,
        ): Promise<void> {
            const verb = this.details;
            this.details = null;

            if (!verb) {
                return;
            }

            try {
                await save(verb.v1);
            } catch {
                // send() has told the learner; the list stays as it was.
                return;
            }

            this.showList();
        },
    },
});
</script>
