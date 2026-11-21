<template>
    <ChipTag kind="lvl" :color="info?.color">
        {{ info ? info.name : level.toUpperCase() }}
    </ChipTag>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import ChipTag from '@/components/general/ChipTag.vue';
import type { LevelId } from '@/types';
import type { LevelInfo } from '@/utils/levels';
import { levelInfo } from '@/utils/levels';

/** "B1" chip in the colour of the level. */
export default defineComponent({
    name: 'LevelChip',

    components: { ChipTag },

    props: {
        level: { type: String as PropType<LevelId>, required: true },
    },

    computed: {
        info(): LevelInfo | null {
            return levelInfo(this.level);
        },
    },
});
</script>
