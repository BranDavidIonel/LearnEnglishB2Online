<template>
    <div class="page-head">
        <Link v-if="back" :href="back" class="back">← {{ $t('Back') }}</Link>
        <h1>{{ title }}</h1>
        <p v-if="sub" class="muted">{{ sub }}</p>
    </div>
</template>

<script lang="ts">
import { Link } from '@inertiajs/vue3';
import { defineComponent } from 'vue';

/** Title of a page, with an optional line under it and an optional "← Back" link above it. */
export default defineComponent({
    name: 'PageHeader',

    components: { Link },

    props: {
        title: { type: String, required: true },
        sub: { type: String, default: '' },
        /** URL of the "← Back" link (a Wayfinder helper's .url()). */
        back: { type: String, default: '' },
    },
});
</script>
