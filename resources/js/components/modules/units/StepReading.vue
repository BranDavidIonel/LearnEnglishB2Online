<template>
    <div class="read">
        <div class="card read-text">
            <h2>📖 {{ reading.title }}</h2>
            <div class="row wrap">
                <button type="button" class="btn" @click="listen">
                    {{ $t('🔊 Listen to the text') }}
                </button>
                <button type="button" class="btn" @click="stop">⏹</button>
                <button type="button" class="btn" @click="showRo = !showRo">
                    {{
                        showRo
                            ? $t('🙈 Hide translation')
                            : $t('🇷🇴 Show translation')
                    }}
                </button>
            </div>
            <div class="read-en" v-html="md(reading.text)" />
            <div
                class="read-ro"
                :class="{ hidden: !showRo }"
                v-html="md(reading.textRo)"
            />
        </div>
        <div class="read-q">
            <ExerciseSession
                v-if="phase === 'questions'"
                :items="reading.questions"
                mode="practice"
                :title="$t('Questions about the text')"
                @finish="finish"
                @exit="backToUnit"
            />
            <SessionSave v-else-if="phase === 'result' && save" :save="save">
                <ResultCard
                    :score="right / total"
                    :title="
                        right === total
                            ? $t('You understood the whole text! 🎉')
                            : $t('Text done ✓')
                    "
                    :lines="[
                        first
                            ? $t(':ok of :total correct · +:xp XP ⚡', {
                                  ok: right,
                                  total,
                                  xp,
                              })
                            : $t(':ok of :total correct', { ok: right, total }),
                    ]"
                >
                    <template #actions>
                        <Link
                            class="btn primary big"
                            :href="
                                stepRoute({ unit: unit.id, step: 'test' }).url
                            "
                        >
                            {{ $t('Next: Unit test →') }}
                        </Link>
                    </template>
                </ResultCard>
            </SessionSave>
        </div>
    </div>
</template>

<script lang="ts">
import { Link, router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent, markRaw } from 'vue';
import { completeStep } from '@/api/progress';
import ExerciseSession from '@/components/exercise/ExerciseSession.vue';
import ResultCard from '@/components/exercise/ResultCard.vue';
import SessionSave from '@/components/general/SessionSave.vue';
import { show as unitRoute, step as stepRoute } from '@/routes/units';
import type { ExerciseResult } from '@/types';
import type { SaveQueue } from '@/utils/saving';
import { saveQueue } from '@/utils/saving';
import { speak, stopSpeak } from '@/utils/speech';
import { md } from '@/utils/text';
import type { ReadingStep, UnitHead } from './types';

/** Step 5: a short text (read aloud on request, translation on request) and questions about it. */
export default defineComponent({
    name: 'StepReading',

    components: { Link, ExerciseSession, ResultCard, SessionSave },

    props: {
        unit: { type: Object as PropType<UnitHead>, required: true },
        reading: { type: Object as PropType<ReadingStep>, required: true },
    },

    data() {
        return {
            showRo: false,
            phase: 'questions' as 'questions' | 'result',
            right: 0,
            total: 0,
            first: false,
            xp: 0,
            save: null as SaveQueue | null,
        };
    },

    beforeUnmount() {
        stopSpeak();
    },

    methods: {
        md,
        stepRoute,

        listen(): void {
            speak(this.reading.text);
        },

        stop(): void {
            stopSpeak();
        },

        finish(results: ExerciseResult[]): void {
            this.right = results.filter((result) => result.correct).length;
            this.total = results.length;
            this.save = markRaw(
                saveQueue([
                    async () => {
                        const answer = await completeStep(
                            this.unit.id,
                            'reading',
                        );
                        this.first = answer.first;
                        this.xp = answer.xp;
                    },
                ]),
            );
            this.phase = 'result';
        },

        backToUnit(): void {
            router.visit(unitRoute(this.unit.id).url);
        },
    },
});
</script>
