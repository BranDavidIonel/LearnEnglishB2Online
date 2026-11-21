<template>
    <div>
        <VerbCard :verb="verb" @help="$emit('help')" />
        <div class="center mt">
            <ChipTag :kind="STATUS[verb.status].kind">
                {{ $t(STATUS[verb.status].label) }}
            </ChipTag>
            <span v-if="verb.known" class="muted small">
                {{ ' ' }}{{ $t('· you already knew it at the check') }}
            </span>
        </div>
        <div class="row center wrap mt">
            <button
                v-if="verb.status !== 'learned'"
                class="btn primary"
                @click="$emit('learn')"
            >
                {{ $t('🧠 Learn it now') }}
            </button>
            <button
                v-if="verb.status === 'new'"
                class="btn"
                @click="$emit('check')"
            >
                {{ $t('⚡ Test me') }}
            </button>
            <button
                v-if="verb.status === 'learned'"
                class="btn"
                @click="$emit('again')"
            >
                {{ $t('↺ Practise it again') }}
            </button>
            <button
                v-if="verb.status !== 'new'"
                class="btn"
                @click="$emit('forget')"
            >
                {{ $t('Remove from review') }}
            </button>
        </div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ChipTag from '@/components/general/ChipTag.vue';
import { STATUS } from '@/components/modules/words/status';
import VerbCard from './VerbCard.vue';
import type { VerbItem } from './verbs';
import { sayForms } from './verbs';

/**
 * What the modal of a verb shows: its card, its status and what can be done with it
 * (learn it now, test me, practise it again, remove it from the review). The page does the work.
 */
export default defineComponent({
    name: 'VerbDetails',

    components: { ChipTag, VerbCard },

    props: {
        verb: { type: Object as PropType<VerbItem>, required: true },
    },

    emits: ['learn', 'check', 'again', 'forget', 'help'],

    data() {
        return {
            STATUS,
        };
    },

    mounted() {
        sayForms(this.verb);
    },
});
</script>
