<template>
    <button
        type="button"
        class="icon-btn speak"
        :title="$t('Listen')"
        @click.stop="play"
    >
        🔊
    </button>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { speak } from '@/utils/speech';

/** 🔊 button that reads an English text aloud. Extra classes go on the tag (class="big"). */
export default defineComponent({
    name: 'SpeakButton',

    props: {
        text: { type: String, required: true },
    },

    methods: {
        play(): void {
            speak(this.text);
        },
    },
});
</script>
