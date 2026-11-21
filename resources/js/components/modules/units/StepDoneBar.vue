<template>
    <SessionSave v-if="save" :save="save" @saved="next" />
    <div class="row center mt">
        <button
            type="button"
            class="btn primary big"
            :disabled="save !== null"
            @click="done"
        >
            {{ label }}
        </button>
    </div>
</template>

<script lang="ts">
import { router } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent, markRaw } from 'vue';
import { completeStep } from '@/api/progress';
import { step as stepRoute } from '@/routes/units';
import type { LessonStep } from '@/types';
import { sfx } from '@/utils/sound';
import { toast } from '@/utils/toast';
import type { ResumableSave } from './resumableSave';
import { resumableSave } from './resumableSave';
import SessionSave from './SessionSave.vue';
import { nextStep } from './steps';

/**
 * "Got it ✓ — next: …" under a step that is done by reading it (grammar, dialogue): marks the
 * step done (XP the first time) and goes on to the next step. When it cannot be saved, it says
 * so and offers to try again instead of moving on.
 */
export default defineComponent({
    name: 'StepDoneBar',

    components: { SessionSave },

    props: {
        unitId: { type: String, required: true },
        step: { type: String as PropType<LessonStep>, required: true },
        label: { type: String, required: true },
    },

    data() {
        return {
            save: null as ResumableSave | null,
        };
    },

    methods: {
        done(): void {
            this.save = markRaw(
                resumableSave([
                    async () => {
                        const answer = await completeStep(
                            this.unitId,
                            this.step,
                        );

                        if (answer.first) {
                            sfx('ok');
                            toast(`+${answer.xp} XP ⚡`);
                        }
                    },
                ]),
            );
        },

        next(): void {
            router.visit(
                stepRoute({
                    unit: this.unitId,
                    step: nextStep(this.step) ?? 'test',
                }).url,
            );
        },
    },
});
</script>
