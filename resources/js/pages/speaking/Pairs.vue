<template>
    <Head :title="$t('Sound-alike words')" />
    <PairsRound :key="run" :back="speaking().url" @again="run++" />
</template>

<script lang="ts">
import { Head } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import PairsRound from '@/components/modules/speaking/PairsRound.vue';
import { speaking } from '@/routes';

/** Sound-alike words (ship / sheep), eight pairs a round. */
export default defineComponent({
    name: 'SpeakingPairs',

    components: { Head, PairsRound },

    data() {
        return {
            run: 0,
        };
    },

    methods: {
        speaking,
    },
});
</script>
