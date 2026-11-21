<template>
    <div class="result">
        <slot />
        <h2>{{ title }}</h2>
        <p class="muted">{{ line }}</p>
        <div class="row center">
            <button class="btn primary big" @click="$emit('again')">
                {{ again }}
            </button>
            <Link class="btn" :href="games().url">{{ $t('More games') }}</Link>
        </div>
    </div>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import { defineComponent } from 'vue';
import { games } from '@/routes';

/** The end of a game round: the score (default slot), a title, the XP line, "play again" and "More games". */
export default defineComponent({
    name: 'GameResult',

    components: { Link },

    props: {
        title: { type: String, required: true },
        line: { type: String, required: true },
        /** Label of the button that starts a new round. */
        again: { type: String, required: true },
    },

    emits: ['again'],

    methods: {
        games,
    },
});
</script>
