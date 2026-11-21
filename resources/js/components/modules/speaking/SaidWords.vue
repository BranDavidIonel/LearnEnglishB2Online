<template>
    <span>
        <template v-for="(word, i) in words" :key="i">
            {{ i ? ' ' : ''
            }}<span class="tw" :class="word.hit ? 'hit' : 'miss'">{{
                word.w
            }}</span>
        </template>
    </span>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { SpokenWord } from '@/utils/text';

/** A target sentence with the words that were heard in green and the missed ones underlined. */
export default defineComponent({
    name: 'SaidWords',

    props: {
        words: { type: Array as PropType<SpokenWord[]>, required: true },
    },
});
</script>
