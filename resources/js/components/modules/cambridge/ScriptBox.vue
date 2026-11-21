<template>
    <details class="script">
        <summary>{{ $t('📜 Show the recording script') }}</summary>
        <p v-for="(line, i) in script" :key="i">
            <b>{{ line.s }}: </b>{{ line.en }}
        </p>
        <button type="button" class="btn" @click="replay">
            {{ $t('▶ Listen again') }}
        </button>
    </details>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { ScriptLine } from '@/types';
import { speakScript } from '@/utils/speech';

/** The script of a listening recording, shown after the paper is handed in. */
export default defineComponent({
    name: 'ScriptBox',

    props: {
        script: { type: Array as PropType<ScriptLine[]>, required: true },
    },

    methods: {
        replay(): void {
            speakScript(this.script);
        },
    },
});
</script>
