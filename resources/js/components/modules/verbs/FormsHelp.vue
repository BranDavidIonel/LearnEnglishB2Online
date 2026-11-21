<template>
    <AppModal :open="open" wide @close="$emit('close')">
        <div class="forms-help">
            <GrammarLesson :grammar="grammar" />
        </div>
    </AppModal>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import GrammarLesson from '@/components/content/GrammarLesson.vue';
import AppModal from '@/components/modals/AppModal.vue';
import type { Grammar } from '@/types';
import formsHelp from './forms-help.json';

// The lesson is course content (explanations in Romanian, like the grammar of the units): never translated.
const FORMS_HELP: Grammar = formsHelp;

/** The full explanation of the three forms of irregular verbs, shown like a grammar lesson in a wide modal. */
export default defineComponent({
    name: 'FormsHelp',

    components: { AppModal, GrammarLesson },

    props: {
        open: { type: Boolean, default: false },
    },

    emits: ['close'],

    data() {
        return {
            grammar: FORMS_HELP,
        };
    },
});
</script>
