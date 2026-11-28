<template>
    <div class="card notice">
        <p class="help-intro" v-html="md(guide.introRo)"></p>
    </div>
    <section v-for="group in guide.groups" :key="group.id" class="card">
        <h2>{{ group.titleRo }}</h2>
        <p v-if="group.noteRo" class="muted" v-html="md(group.noteRo)"></p>
        <div class="snd-list">
            <div
                v-for="sound in group.sounds"
                :key="sound.symbol"
                class="snd-row"
            >
                <div class="snd-sym">{{ sound.symbol }}</div>
                <div class="snd-body">
                    <strong>{{ sound.likeRo }}</strong>
                    <p
                        v-if="sound.textRo"
                        class="snd-text"
                        v-html="md(sound.textRo)"
                    ></p>
                    <div v-if="sound.examples.length" class="snd-examples">
                        <span
                            v-for="example in sound.examples"
                            :key="example.en"
                            class="snd-ex"
                        >
                            <SpeakButton :text="example.en" />
                            <TapText :text="example.en" tag="strong" />
                            <span
                                class="snd-ipa"
                                v-html="mark(example.ipa, sound.symbol)"
                            ></span>
                            <small class="muted">{{ example.ro }}</small>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import TapText from '@/components/content/TapText.vue';
import { markSound } from '@/components/modules/help/sounds';
import type { SoundGuideData } from '@/components/modules/help/types';
import SpeakButton from '@/components/speech/SpeakButton.vue';
import { md } from '@/utils/text';

/** The pronunciation signs of the course, by group: what each sounds like in Romanian, with course words to hear. */
export default defineComponent({
    name: 'SoundGuide',

    components: { SpeakButton, TapText },

    props: {
        guide: { type: Object as PropType<SoundGuideData>, required: true },
    },

    methods: {
        md,

        mark(ipa: string, symbol: string): string {
            return markSound(ipa, symbol);
        },
    },
});
</script>
