<template>
    <div v-if="next.kind === 'welcome'" class="card hero">
        <div class="hero-emoji">👋</div>
        <h2>{{ $t("Welcome! Let's see where you're starting from.") }}</h2>
        <p>
            {{
                $t(
                    'Take a short test (10–15 minutes) so I can find your level. If you already know some English, you can skip straight to the right level.',
                )
            }}
        </p>
        <div class="row wrap">
            <Link class="btn primary big" :href="placementUrl">
                {{ $t('🧭 Take the placement test') }}
            </Link>
            <Link class="btn big" :href="unitUrl">
                {{ $t('▶ Start from scratch (A1)') }}
            </Link>
        </div>
    </div>

    <div
        v-else-if="next.kind === 'unit' && next.unit && next.level"
        class="card hero"
        :style="{ '--c': next.level.color }"
    >
        <div class="hero-emoji">{{ next.unit.emoji }}</div>
        <div class="muted">
            {{
                next.review
                    ? $t('⚠️ Review before the exam')
                    : $t('Continue · :level · Unit :n', {
                          level: next.level.name,
                          n: next.unit.order,
                      })
            }}
        </div>
        <h2>{{ next.unit.title }}</h2>
        <p class="muted">{{ next.unit.titleRo }}</p>
        <p>
            {{ $t('Next step:') }} <strong>{{ stepName }}</strong>
        </p>
        <div class="row wrap">
            <Link class="btn primary big start" :href="stepUrl">▶ START</Link>
            <Link class="btn" :href="unitUrl">{{ $t('View unit') }}</Link>
        </div>
    </div>

    <div
        v-else-if="next.kind === 'exam' && next.level"
        class="card hero"
        :style="{ '--c': next.level.color }"
    >
        <div class="hero-emoji">🎓</div>
        <h2>
            {{
                $t("You're ready for the :level level exam!", {
                    level: next.level.name,
                })
            }}
        </h2>
        <p>
            {{
                $t(
                    "You've finished all the units. Pass the exam with at least :pct% to unlock the next level.",
                    { pct: Math.round(passMark * 100) },
                )
            }}
        </p>
        <Link class="btn primary big start" :href="examUrl">
            {{ $t('▶ Start the exam') }}
        </Link>
    </div>

    <div v-else class="card hero">
        <div class="hero-emoji">🏆</div>
        <h2>{{ $t("Congratulations! You've finished all the levels.") }}</h2>
        <p>
            {{
                $t(
                    'Keep going with flashcards, games and Cambridge practice tests to stay in shape.',
                )
            }}
        </p>
        <Link class="btn primary big" :href="cambridgeUrl">
            {{ $t('📝 Cambridge practice tests') }}
        </Link>
    </div>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { NextStep } from '@/components/modules/home/types';
import { cambridge, learn, placement } from '@/routes';
import exams from '@/routes/exams';
import units from '@/routes/units';
import type { UnitStep } from '@/types';

/** The big card on top of the home page: what to do next, with one button that starts it. */
export default defineComponent({
    name: 'NextCard',

    components: { Link },

    props: {
        next: { type: Object as PropType<NextStep>, required: true },
        /** Pass mark of the level exam, 0..1. */
        passMark: { type: Number, required: true },
    },

    computed: {
        stepName(): string {
            const names: Record<UnitStep, string> = {
                vocab: this.$t('New words'),
                grammar: this.$t('Grammar'),
                dialogue: this.$t('Dialogue'),
                practice: this.$t('Exercises'),
                reading: this.$t('Reading'),
                test: this.$t('Unit test'),
            };

            return this.next.step ? names[this.next.step] : '';
        },

        unitUrl(): string {
            // A newcomer with no unit to start (an empty course) is sent to the course map.
            return this.next.unit
                ? units.show(this.next.unit.id).url
                : learn().url;
        },

        stepUrl(): string {
            return this.next.unit && this.next.step
                ? units.step({ unit: this.next.unit.id, step: this.next.step })
                      .url
                : this.unitUrl;
        },

        examUrl(): string {
            return this.next.level
                ? exams.show(this.next.level.id).url
                : learn().url;
        },

        placementUrl(): string {
            return placement().url;
        },

        cambridgeUrl(): string {
            return cambridge().url;
        },
    },
});
</script>
