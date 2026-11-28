<template>
    <div class="grid2 tips-grid">
        <div v-for="tip in tips" :key="tip.titleRo" class="card tip-card">
            <span class="plan-emoji">{{ tip.emoji }}</span>
            <div>
                <strong>{{ tip.titleRo }}</strong>
                <p v-html="md(tip.textRo)"></p>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { StudyTip } from '@/components/modules/help/types';
import { md } from '@/utils/text';

/** What helps besides the app: one card per piece of advice. */
export default defineComponent({
    name: 'StudyTips',

    props: {
        tips: { type: Array as PropType<StudyTip[]>, required: true },
    },

    methods: { md },
});
</script>
