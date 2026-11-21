<template>
    <div class="sess-title">{{ title }}</div>
    <p class="muted center">
        {{
            $t(
                'Look, listen and say the three forms aloud · ← → to navigate · Space = audio',
            )
        }}
    </p>
    <div class="vcard">
        <VerbCard :key="index" :verb="current" @help="$emit('help')" />
    </div>
    <div class="dots">
        <span
            v-for="(verb, n) in verbs"
            :key="verb.v1"
            class="dot"
            :class="{ now: n === index, done: n < index }"
            @click="index = n"
        />
    </div>
    <div class="row center">
        <button class="btn" :disabled="index === 0" @click="previous">
            {{ $t('← Back') }}
        </button>
        <button class="btn primary" @click="next">
            {{ isLast ? $t('Now type them →') : $t('Next →') }}
        </button>
    </div>
</template>

<script lang="ts">
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import { stopSpeak } from '@/utils/speech';
import VerbCard from './VerbCard.vue';
import type { VerbItem } from './verbs';
import { sayForms } from './verbs';

/**
 * Look and listen: one card per verb, read aloud as it appears; ← → move, Space reads it again.
 * After the last card it emits `done` (the drill follows).
 */
export default defineComponent({
    name: 'VerbStudy',

    components: { VerbCard },

    props: {
        verbs: { type: Array as PropType<VerbItem[]>, required: true },
        title: { type: String, required: true },
    },

    emits: ['done', 'help'],

    data() {
        return {
            index: 0,
        };
    },

    computed: {
        current(): VerbItem {
            return this.verbs[this.index];
        },

        isLast(): boolean {
            return this.index === this.verbs.length - 1;
        },
    },

    watch: {
        index: {
            immediate: true,
            handler(): void {
                sayForms(this.current);
            },
        },
    },

    mounted() {
        document.addEventListener('keydown', this.onKeydown);
    },

    beforeUnmount() {
        document.removeEventListener('keydown', this.onKeydown);
        stopSpeak();
    },

    methods: {
        previous(): void {
            if (this.index > 0) {
                this.index--;
            }
        },

        next(): void {
            if (this.isLast) {
                this.$emit('done');
            } else {
                this.index++;
            }
        },

        onKeydown(event: KeyboardEvent): void {
            if ((event.target as HTMLElement | null)?.tagName === 'INPUT') {
                return;
            }

            if (event.key === 'ArrowRight') {
                this.next();
            } else if (event.key === 'ArrowLeft') {
                this.previous();
            } else if (event.key === ' ') {
                event.preventDefault();
                sayForms(this.current);
            }
        },
    },
});
</script>
