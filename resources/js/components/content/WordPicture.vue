<template>
    <div class="pic" :class="{ 'has-img': !!src }">
        <img v-if="src" :src="src" :alt="word.en" />
        <span class="pic-emoji">{{ word.emoji || '📘' }}</span>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { PictureSubject } from '@/types';
import { wikiImage } from '@/utils/wiki';

/** A word's picture: the emoji at once, replaced by the Wikipedia photo once it has loaded. */
export default defineComponent({
    name: 'WordPicture',

    props: {
        word: { type: Object as PropType<PictureSubject>, required: true },
    },

    data() {
        return {
            src: '',
            // Ignores a photo that arrives after the word has changed or the picture is gone.
            request: 0,
        };
    },

    watch: {
        'word.wiki': {
            immediate: true,
            handler(): void {
                void this.load();
            },
        },
    },

    beforeUnmount() {
        this.request++;
    },

    methods: {
        async load(): Promise<void> {
            const request = ++this.request;
            this.src = '';
            const src = await wikiImage(this.word.wiki);

            if (!src || request !== this.request) {
                return;
            }

            // Show the photo only once it has loaded, so a broken one never replaces the emoji.
            const image = new Image();
            image.onload = () => {
                if (request === this.request) {
                    this.src = src;
                }
            };
            image.src = src;
        },
    },
});
</script>
