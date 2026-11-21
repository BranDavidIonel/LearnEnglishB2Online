<template>
    <Head :title="$t('Learn')" />
    <PageHeader
        :title="$t('Learn')"
        :sub="
            $t(
                'Work through the units in order. Each unit ends with a test; each level ends with an exam.',
            )
        "
    />
    <LevelSection
        v-for="(item, index) in levels"
        :key="item.level.id"
        :item="item"
        :previous="index > 0 ? levels[index - 1].level.name : null"
        :batch="batch"
        :pass-mark="passMark"
    />
</template>

<script lang="ts">
import { Head } from '@inertiajs/vue3';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import LevelSection from '@/components/modules/home/LevelSection.vue';
import type { LearnLevel } from '@/components/modules/home/types';

/** The learning path: every level with its units and its exam, in course order. */
export default defineComponent({
    name: 'LearnPage',

    components: { Head, LevelSection, PageHeader },

    props: {
        levels: { type: Array as PropType<LearnLevel[]>, required: true },
        /** Words handed out per quick-sort batch. */
        batch: { type: Number, required: true },
        /** Pass mark of a level exam, 0..1. */
        passMark: { type: Number, required: true },
    },
});
</script>
