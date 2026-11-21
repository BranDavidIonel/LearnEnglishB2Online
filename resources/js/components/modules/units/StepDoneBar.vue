<template>
    <SessionSave
        v-if="save"
        :save="save"
        :keeps-answers="false"
        @saved="next"
    />
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
import SessionSave from '@/components/general/SessionSave.vue';
import { step as stepRoute } from '@/routes/units';
import type { LessonStep } from '@/types';
import type { SaveQueue } from '@/utils/saving';
import { saveQueue } from '@/utils/saving';
import { sfx } from '@/utils/sound';
import { toast } from '@/utils/toast';
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
            save: null as SaveQueue | null,
        };
    },

    methods: {
        done(): void {
            this.save = markRaw(
                saveQueue([
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
