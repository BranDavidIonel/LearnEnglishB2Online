<template>
    <div class="vcard-in">
        <WordPicture :word="word" class="big" />
        <div class="vword">
            <span>{{ word.en }}</span>
            <SpeakButton :text="word.en" />
        </div>
        <div class="vipa">
            {{ word.ipa }} · <em>{{ word.pos }}</em>
        </div>
        <div class="vro">{{ word.ro }}</div>
        <div class="vex">
            <SpeakButton :text="word.ex" />
            <div>
                <div class="en">{{ word.ex }}</div>
                <div class="ro">{{ word.exRo }}</div>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import WordPicture from '@/components/content/WordPicture.vue';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import type { VocabWord } from '@/types';

/** A word as a card: picture, the word with its pronunciation, the meaning and an example. */
export default defineComponent({
    name: 'WordCard',

    components: { SpeakButton, WordPicture },

    props: {
        word: { type: Object as PropType<VocabWord>, required: true },
    },
});
</script>
